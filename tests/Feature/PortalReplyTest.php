<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\PortalLink;
use App\Notifications\TicketReceived;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/*
 * A POST needs identity and CSRF, not a signature in the query string (§3):
 * a signature on this route is worth nothing without the portal session
 * behind it.
 */
test('a signature alone cannot reply', function () {
    $ticket = Ticket::factory()->inAttesa()->create();

    $signed = URL::temporarySignedRoute(
        'support.ticket.reply',
        now()->addDays(7),
        ['ticket' => $ticket],
    );

    $this->post($signed, ['body' => 'Ancora presente.'])->assertForbidden();

    assertDatabaseCount('ticket_messages', 0);
});

test('a reply is refused without a portal session', function () {
    $ticket = Ticket::factory()->inAttesa()->create();

    $this->post(route('support.ticket.reply', $ticket), ['body' => 'Ancora presente.'])
        ->assertForbidden();

    assertDatabaseCount('ticket_messages', 0);
});

/*
 * The one filter between two customers (§3): being logged in as somebody
 * else is worth nothing here.
 */
test('a reply is refused on somebody else\'s ticket', function () {
    $requester = User::factory()->requester()->create();
    $ticket = Ticket::factory()->inAttesa()->for(
        User::factory()->requester()->create(['email' => 'mario.bianchi@example.com']),
        'requester',
    )->create();

    $this->get(PortalLink::linkTo($requester));

    $this->post(route('support.ticket.reply', $ticket), ['body' => 'Ancora presente.'])
        ->assertForbidden();

    assertDatabaseCount('ticket_messages', 0);
});

test('an empty reply is refused', function () {
    $requester = User::factory()->requester()->create();
    $ticket = Ticket::factory()->inAttesa()->for($requester, 'requester')->create();

    $this->get(PortalLink::linkTo($requester));

    $this->post(route('support.ticket.reply', $ticket), ['body' => ''])
        ->assertSessionHasErrors('body');
});

test('a reply from the portal resumes a ticket waiting on the requester', function () {
    $requester = User::factory()->requester()->create();
    $ticket = Ticket::factory()->inAttesa()->for($requester, 'requester')->create();

    $this->get(PortalLink::linkTo($requester));

    $this->post(route('support.ticket.reply', $ticket), ['body' => 'Succede ancora.'])
        ->assertRedirect(route('support.ticket.show', $ticket));

    expect($ticket->fresh()->status)->toBe(TicketStatus::InLavorazione);

    assertDatabaseHas('ticket_messages', [
        'ticket_id' => $ticket->id,
        'body' => 'Succede ancora.',
    ]);
});

test('a reply from the portal to a closed ticket redirects to the new one it opened', function () {
    $requester = User::factory()->requester()->create();
    $ticket = Ticket::factory()->chiuso()->for($requester, 'requester')->create();

    $this->get(PortalLink::linkTo($requester));

    $response = $this->post(route('support.ticket.reply', $ticket), ['body' => 'Si ripresenta.']);

    $followUp = Ticket::query()->where('parent_ticket_id', $ticket->id)->sole();

    $response->assertRedirect(route('support.ticket.show', $followUp));

    expect($ticket->fresh()->status)->toBe(TicketStatus::Chiuso);
});

test('the portal session can reply and the signed link cannot', function () {
    $requester = User::factory()->requester()->create();
    $ticket = Ticket::factory()->inAttesa()->for($requester, 'requester')->create();

    $this->get(TicketReceived::linkTo($ticket))
        ->assertInertia(fn ($page) => $page->where('canReply', false));

    $this->get(PortalLink::linkTo($requester));

    $this->get(route('support.ticket.show', $ticket))
        ->assertInertia(fn ($page) => $page->where('canReply', true));
});
