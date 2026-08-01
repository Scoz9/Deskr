<?php

use App\Actions\Tickets\ReplyFromRequester;
use App\Actions\Tickets\RequesterReply;
use App\Enums\TicketActorType;
use App\Enums\TicketChannel;
use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

/**
 * Run the use case the way the portal and the email adapter both will:
 * resolved from the container and handed a DTO.
 */
function replyFromRequester(
    Ticket $ticket,
    string $body = 'Aggiungo un dettaglio.',
    TicketChannel $channel = TicketChannel::Web,
    ?string $externalMessageId = null,
): Ticket {
    return app(ReplyFromRequester::class)(new RequesterReply(
        ticket: $ticket,
        requester: $ticket->requester,
        body: $body,
        channel: $channel,
        externalMessageId: $externalMessageId,
    ));
}

/*
 * The requester answering resumes a ticket that was never solved (§4): the
 * same arrow `TransitionTicket` already admits from `in attesa`.
 */
test('a reply while waiting on the requester resumes the ticket', function () {
    $ticket = Ticket::factory()->inAttesa()->create();

    $result = replyFromRequester($ticket, 'Confermo che succede ancora.');

    expect($result->id)->toBe($ticket->id)
        ->and($result->status)->toBe(TicketStatus::InLavorazione);

    assertDatabaseHas('ticket_messages', [
        'ticket_id' => $ticket->id,
        'body' => 'Confermo che succede ancora.',
        'author_id' => $ticket->requester_id,
        'is_internal' => false,
    ]);

    assertDatabaseHas('ticket_events', [
        'ticket_id' => $ticket->id,
        'type' => TicketEventType::Ripreso->value,
        'actor_id' => $ticket->requester_id,
        'actor_kind' => TicketActorType::Utente->value,
    ]);
});

/*
 * There is no `riaperto` status: reopening is the `risolto` → `in
 * lavorazione` arrow, and `reopen_count` is what the reopening rate is
 * counted on.
 */
test('a reply to a solved ticket reopens it', function () {
    $ticket = Ticket::factory()->risolto()->create();

    $result = replyFromRequester($ticket, 'Il problema si ripresenta.');

    expect($result->status)->toBe(TicketStatus::InLavorazione)
        ->and($result->reopen_count)->toBe(1)
        ->and($result->resolved_at)->toBeNull();

    assertDatabaseHas('ticket_events', [
        'ticket_id' => $ticket->id,
        'type' => TicketEventType::Riaperto->value,
    ]);
});

/*
 * A closed ticket does not resuscitate months later and falsify the
 * resolution and backlog metrics (§3): the reply fathers a new ticket
 * instead.
 */
test('a reply to a closed ticket opens a new one instead of reopening it', function () {
    $ticket = Ticket::factory()->chiuso()->create(['subject' => 'La stampante non risponde']);

    $followUp = replyFromRequester($ticket, 'Il problema si ripresenta.');

    expect($followUp->id)->not->toBe($ticket->id)
        ->and($followUp->status)->toBe(TicketStatus::Nuovo)
        ->and($followUp->parent_ticket_id)->toBe($ticket->id)
        ->and($followUp->subject)->toBe('La stampante non risponde')
        ->and($followUp->requester_id)->toBe($ticket->requester_id)
        ->and($followUp->messages)->toHaveCount(1)
        ->and($followUp->messages->first()->body)->toBe('Il problema si ripresenta.');

    expect($ticket->fresh()->status)->toBe(TicketStatus::Chiuso);

    assertDatabaseCount('ticket_messages', 1);
});

/*
 * A closed ticket routes the follow-up the same way its category already
 * does: re-filing is a new intake, and reading the category again here is
 * not the re-routing the domain refuses to do later (§4).
 */
test('the follow-up of a closed ticket is routed like its parent', function () {
    $category = Category::factory()->create();
    $ticket = Ticket::factory()->chiuso()->create(['category_id' => $category->id, 'team_id' => $category->team_id]);

    $followUp = replyFromRequester($ticket);

    expect($followUp->category_id)->toBe($category->id)
        ->and($followUp->team_id)->toBe($category->team_id);
});

/*
 * A reply is not a passage everywhere: a ticket already alive with nobody
 * waiting on the requester just gets the message appended to its thread.
 */
test('a reply to a live ticket is appended without moving it', function (TicketStatus $status) {
    $factory = match ($status) {
        TicketStatus::Nuovo => Ticket::factory()->nuovo(),
        TicketStatus::Assegnato => Ticket::factory()->assegnato(),
        TicketStatus::InLavorazione => Ticket::factory()->inLavorazione(),
        TicketStatus::Annullato => Ticket::factory()->annullato(),
    };

    $ticket = $factory->create();

    $result = replyFromRequester($ticket, 'Aggiungo che succede solo dal portatile.');

    expect($result->id)->toBe($ticket->id)
        ->and($result->status)->toBe($status);

    assertDatabaseHas('ticket_messages', [
        'ticket_id' => $ticket->id,
        'body' => 'Aggiungo che succede solo dal portatile.',
    ]);

    assertDatabaseCount('ticket_events', 0);
})->with([
    'received' => [TicketStatus::Nuovo],
    'picked up' => [TicketStatus::Assegnato],
    'being worked on' => [TicketStatus::InLavorazione],
    'cancelled' => [TicketStatus::Annullato],
]);

/*
 * The requester never starts the first response metric (§5): "operator" is
 * the spatie role, and a requester replying to their own ticket is not one.
 */
test('the requester replying never starts the first response metric', function () {
    $ticket = Ticket::factory()->nuovo()->create();

    replyFromRequester($ticket);

    expect($ticket->fresh()->first_response_at)->toBeNull();
});

test('the requester replying does not touch a first response already on record', function () {
    $ticket = Ticket::factory()->inAttesa()->create();
    $firstResponse = $ticket->first_response_at;

    replyFromRequester($ticket);

    expect($ticket->fresh()->first_response_at->equalTo($firstResponse))->toBeTrue();
});

/*
 * The follow-up of a closed ticket is born on the channel the reply itself
 * came in on (step 29), not necessarily the one the parent ticket did.
 */
test('the follow-up of a closed ticket carries the channel the reply came in on', function () {
    $ticket = Ticket::factory()->chiuso()->create();

    $followUp = replyFromRequester($ticket, channel: TicketChannel::Email);

    expect($followUp->channel)->toBe(TicketChannel::Email);
});

/*
 * The id of a threaded inbound email is what a later reply's `In-Reply-To`
 * will match against (step 29) — it has to survive onto the message the
 * reply writes, and onto the follow-up a closed ticket fathers.
 */
test('a threaded reply carries the id of the email it came from', function () {
    $ticket = Ticket::factory()->inAttesa()->create();

    replyFromRequester($ticket, externalMessageId: '<abc123@mail.example.com>');

    assertDatabaseHas('ticket_messages', [
        'ticket_id' => $ticket->id,
        'external_message_id' => '<abc123@mail.example.com>',
    ]);
});

test('the follow-up of a closed ticket carries the id of the email that opened it', function () {
    $ticket = Ticket::factory()->chiuso()->create();

    $followUp = replyFromRequester($ticket, externalMessageId: '<abc123@mail.example.com>');

    assertDatabaseHas('ticket_messages', [
        'ticket_id' => $followUp->id,
        'external_message_id' => '<abc123@mail.example.com>',
    ]);
});
