<?php

use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/*
 * One of the three flows the brief sends end to end (§5): everything between
 * the form and the ticket — the client validation, the Inertia visit, the
 * server rules, the Action — is covered a layer lower, and none of those layers
 * can tell whether the whole thing actually works from a browser.
 *
 * The headless browser asks for en-US, so the page answers in English.
 */
test('a request typed into the public form comes back as a ticket with its reference', function () {
    $this->seed(RoleSeeder::class);
    $category = Category::factory()->create(['name' => 'Rete']);

    $page = visit(route('support.create'));

    $page->fill('input[name=name]', 'Anna Rossi')
        ->fill('input[name=email]', 'anna.rossi@example.com')
        ->select('select[name=categoryId]', (string) $category->id)
        ->fill('input[name=subject]', 'La stampante non risponde')
        ->fill('textarea[name=body]', 'Da stamattina la stampante del secondo piano non stampa.')
        ->click('Send the request')
        ->assertSee('Request received.')
        ->assertNoJavascriptErrors();

    $ticket = Ticket::sole();

    expect($ticket->status)->toBe(TicketStatus::Nuovo)
        ->and($ticket->channel)->toBe(TicketChannel::Web)
        ->and($ticket->team_id)->toBe($category->team_id)
        ->and($ticket->messages)->toHaveCount(1)
        ->and(User::sole()->email)->toBe('anna.rossi@example.com');

    $page->assertSee($ticket->reference);
});

/*
 * The browser check is what a person actually meets: an incomplete form has to
 * say so on the page, not on the way back from the server.
 */
test('an incomplete form is answered without leaving the page', function () {
    Category::factory()->create();

    $page = visit(route('support.create'));

    $page->click('Send the request')
        ->assertSee('This field is needed.')
        ->assertNoJavascriptErrors();

    expect(Ticket::query()->count())->toBe(0);
});
