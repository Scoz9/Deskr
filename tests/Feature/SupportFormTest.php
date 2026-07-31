<?php

use App\Models\Category;
use App\Models\User;

/*
 * The public intake is the one surface of the application that answers before
 * anybody has an account: a requester never registers (§3), so the form has to
 * open for a guest and stay open for one.
 */
test('a guest can open the public form', function () {
    $this->get(route('support.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('support/create'));
});

test('an authenticated user opening the form is not sent to the console', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('support.create'))->assertOk();
});

/*
 * The category is what routes the ticket to a team (§3), so the form has to be
 * able to ask for it: the options come from the server, in the order they are
 * read in.
 */
test('the form is given the categories to choose from, by name', function () {
    Category::factory()->create(['name' => 'Rete']);
    Category::factory()->create(['name' => 'Accessi']);

    $this->get(route('support.create'))
        ->assertInertia(fn ($page) => $page
            ->component('support/create')
            ->where('categories', fn ($categories) => collect($categories)->pluck('name')->all() === ['Accessi', 'Rete'])
        );
});

test('the categories carry nothing beyond what the form has to render', function () {
    Category::factory()->create(['name' => 'Rete']);

    $this->get(route('support.create'))
        ->assertInertia(fn ($page) => $page
            ->where('categories', fn ($categories) => collect($categories)->first() !== null
                && array_keys((array) collect($categories)->first()) === ['id', 'name'])
        );
});

/*
 * The intake is open to the internet, and a form nobody has to log into is a
 * form a script can hammer. The limit is per IP because there is nothing else
 * to key on until step 23 gives the submission an email address.
 */
test('the public intake is rate limited', function () {
    foreach (range(1, 20) as $attempt) {
        $this->get(route('support.create'))->assertOk();
    }

    $this->get(route('support.create'))->assertStatus(429);
});

test('the rate limit counts one visitor at a time', function () {
    foreach (range(1, 20) as $attempt) {
        $this->get(route('support.create'))->assertOk();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->get(route('support.create'))
        ->assertOk();
});
