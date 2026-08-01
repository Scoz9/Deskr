<?php

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\PortalLink;
use Database\Seeders\RoleSeeder;

/*
 * The second of the three flows the brief sends end to end (§5): the way into
 * the portal is a link and not a password, and whether that actually opens
 * anything is something no test below the browser can answer.
 *
 * The headless browser asks for en-US, so the pages answer in English.
 */
test('the link opens the portal on the requests of whoever asked, and nobody else', function () {
    $this->seed(RoleSeeder::class);

    $requester = User::factory()->requester()->create();
    $mine = Ticket::factory()->for($requester, 'requester')->create([
        'subject' => 'La stampante non risponde',
    ]);
    Ticket::factory()->create(['subject' => 'Il badge non apre il tornello']);

    $page = visit(PortalLink::linkTo($requester));

    $page->assertSee('My requests')
        ->assertSee('La stampante non risponde')
        ->assertDontSee('Il badge non apre il tornello')
        ->click('La stampante non risponde')
        ->assertSee($mine->reference)
        ->assertNoJavascriptErrors();
});

test('asking for a link answers without saying whether the address is known', function () {
    $this->seed(RoleSeeder::class);

    $page = visit(route('portal.request'));

    $page->fill('input[name=email]', 'nessuno@example.com')
        ->click('Send me the link')
        ->assertSee('If the address is on our list')
        ->assertNoJavascriptErrors();
});
