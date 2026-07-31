<?php

use App\Actions\Tickets\TicketTransitionRequest;
use App\Actions\Tickets\TransitionTicket;
use App\Enums\TicketActorType;
use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Tickets\Events\TicketDomainEvent;
use App\Tickets\InvalidTicketTransition;
use App\Tickets\TicketActor;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

/**
 * A ticket sitting in the status the passage starts from.
 */
function ticketToTransition(TicketStatus $status): Ticket
{
    $factory = Ticket::factory();

    return (match ($status) {
        TicketStatus::Nuovo => $factory->nuovo(),
        TicketStatus::Assegnato => $factory->assegnato(),
        TicketStatus::InLavorazione => $factory->inLavorazione(),
        TicketStatus::InAttesa => $factory->inAttesa(),
        TicketStatus::Risolto => $factory->risolto(),
        TicketStatus::Chiuso => $factory->chiuso(),
        TicketStatus::Annullato => $factory->annullato(),
    })->create();
}

/**
 * Run the use case the way the console will: resolved from the container and
 * handed a DTO.
 */
function transitionTicket(Ticket $ticket, TicketStatus $status, ?TicketActor $actor = null): Ticket
{
    return app(TransitionTicket::class)(new TicketTransitionRequest(
        ticket: $ticket,
        status: $status,
        actor: $actor ?? TicketActor::user($ticket->assignee ?? $ticket->requester),
    ));
}

test('the passage moves the ticket and announces itself in the trail', function () {
    $ticket = ticketToTransition(TicketStatus::InLavorazione);

    transitionTicket($ticket, TicketStatus::Risolto);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Risolto);

    assertDatabaseCount('ticket_events', 1);
    assertDatabaseHas('ticket_events', [
        'ticket_id' => $ticket->id,
        'type' => TicketEventType::Risolto->value,
    ]);
});

/*
 * The timestamps of §5, written by the Action that causes the fact. Both arrows
 * that land on `risolto` write it: what the metric measures is the ticket being
 * solved, not which status it was solved from.
 */
test('resolving writes the moment the ticket was solved', function (TicketStatus $from) {
    $ticket = ticketToTransition($from);

    transitionTicket($ticket, TicketStatus::Risolto);

    expect($ticket->fresh()->resolved_at)->not->toBeNull();
})->with([
    'from being worked on' => [TicketStatus::InLavorazione],
    'from waiting on the requester' => [TicketStatus::InAttesa],
]);

test('closing writes its own moment and leaves the resolution where it was', function () {
    $ticket = ticketToTransition(TicketStatus::Risolto);
    $resolvedAt = $ticket->resolved_at;

    transitionTicket($ticket, TicketStatus::Chiuso);

    expect($ticket->fresh()->closed_at)->not->toBeNull()
        ->and($ticket->fresh()->resolved_at->equalTo($resolvedAt))->toBeTrue();
});

/*
 * There is no `riaperto` status: the reopening is the arrow `risolto` → `in
 * lavorazione`, and `reopen_count` is what the reopening rate of step 46 is
 * counted on.
 */
test('reopening counts as a reopening', function () {
    $ticket = ticketToTransition(TicketStatus::Risolto);

    transitionTicket($ticket, TicketStatus::InLavorazione);

    expect($ticket->fresh()->reopen_count)->toBe(1);

    assertDatabaseHas('ticket_events', [
        'ticket_id' => $ticket->id,
        'type' => TicketEventType::Riaperto->value,
    ]);
});

test('a ticket reopened twice is counted twice', function () {
    $ticket = ticketToTransition(TicketStatus::Risolto);

    transitionTicket($ticket, TicketStatus::InLavorazione);
    transitionTicket($ticket, TicketStatus::Risolto);
    transitionTicket($ticket, TicketStatus::InLavorazione);

    expect($ticket->fresh()->reopen_count)->toBe(2);
});

/*
 * A reopened ticket is not solved: leaving the timestamp there would tell the
 * dashboard of step 46 a resolution that is no longer true, and the next
 * resolution writes it again anyway.
 */
test('reopening takes back the resolution', function () {
    $ticket = ticketToTransition(TicketStatus::Risolto);

    transitionTicket($ticket, TicketStatus::InLavorazione);

    expect($ticket->fresh()->resolved_at)->toBeNull();
});

test('a ticket solved again after a reopening carries the later resolution', function () {
    $ticket = ticketToTransition(TicketStatus::Risolto);
    $firstResolution = $ticket->resolved_at;

    transitionTicket($ticket, TicketStatus::InLavorazione);
    transitionTicket($ticket, TicketStatus::Risolto);

    expect($ticket->fresh()->resolved_at)->not->toBeNull()
        ->and($ticket->fresh()->resolved_at->greaterThan($firstResolution))->toBeTrue();
});

/*
 * The same arrow means two different things depending on where it comes from:
 * the requester answering resumes a ticket that was never solved, and counting
 * it as a reopening would inflate the rate with tickets that never came back.
 */
test('resuming a ticket that was waiting is not a reopening', function () {
    $ticket = ticketToTransition(TicketStatus::InAttesa);

    transitionTicket($ticket, TicketStatus::InLavorazione);

    expect($ticket->fresh()->reopen_count)->toBe(0);

    assertDatabaseHas('ticket_events', [
        'ticket_id' => $ticket->id,
        'type' => TicketEventType::Ripreso->value,
    ]);
});

test('taking a ticket in charge measures nothing', function () {
    $ticket = ticketToTransition(TicketStatus::Assegnato);

    transitionTicket($ticket, TicketStatus::InLavorazione);

    expect($ticket->fresh()->reopen_count)->toBe(0)
        ->and($ticket->fresh()->resolved_at)->toBeNull()
        ->and($ticket->fresh()->closed_at)->toBeNull();
});

/*
 * Waiting on the requester and cancelling have no column of their own: the
 * passage is in the trail, and there is no metric to invent for it.
 */
test('a passage with no metric behind it writes no timestamp', function (TicketStatus $from, TicketStatus $to) {
    $ticket = ticketToTransition($from);

    transitionTicket($ticket, $to);

    expect($ticket->fresh()->status)->toBe($to)
        ->and($ticket->fresh()->resolved_at)->toBeNull()
        ->and($ticket->fresh()->closed_at)->toBeNull();
})->with([
    'put on hold' => [TicketStatus::InLavorazione, TicketStatus::InAttesa],
    'cancelled' => [TicketStatus::InLavorazione, TicketStatus::Annullato],
    'back to the pool' => [TicketStatus::Assegnato, TicketStatus::Nuovo],
]);

/*
 * The Action does not decide what is allowed: it asks the table of §4, so that
 * a passage refused there is refused here — and refused before anything is
 * written, on the row and on the model alike.
 */
test('a passage the lifecycle refuses measures nothing and moves nothing', function () {
    $ticket = ticketToTransition(TicketStatus::Chiuso);
    $closedAt = $ticket->closed_at;

    expect(fn () => transitionTicket($ticket, TicketStatus::InLavorazione))
        ->toThrow(InvalidTicketTransition::class);

    expect($ticket->status)->toBe(TicketStatus::Chiuso)
        ->and($ticket->reopen_count)->toBe(0)
        ->and($ticket->fresh()->status)->toBe(TicketStatus::Chiuso)
        ->and($ticket->fresh()->closed_at->equalTo($closedAt))->toBeTrue();

    assertDatabaseCount('ticket_events', 0);
});

/*
 * Status, metric and trail are one fact: a ticket resolved with no line saying
 * so, or a resolution timestamp on a ticket that did not move, is a history
 * that reads back wrong for good.
 */
test('status, metric and trail stand or fall together', function () {
    $ticket = ticketToTransition(TicketStatus::InLavorazione);

    Event::listen(TicketDomainEvent::class, function (): void {
        throw new RuntimeException('the trail could not be written');
    });

    expect(fn () => transitionTicket($ticket, TicketStatus::Risolto))
        ->toThrow(RuntimeException::class);

    expect($ticket->fresh()->status)->toBe(TicketStatus::InLavorazione)
        ->and($ticket->fresh()->resolved_at)->toBeNull();

    assertDatabaseCount('ticket_events', 0);
});

test('the actor of the passage is the one written in the trail', function () {
    $ticket = ticketToTransition(TicketStatus::InAttesa);

    transitionTicket($ticket, TicketStatus::Risolto, TicketActor::system());

    assertDatabaseHas('ticket_events', [
        'ticket_id' => $ticket->id,
        'type' => TicketEventType::Risolto->value,
        'actor_id' => null,
        'actor_kind' => TicketActorType::Sistema->value,
    ]);
});
