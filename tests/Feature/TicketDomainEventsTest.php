<?php

use App\Enums\TicketActorType;
use App\Enums\TicketEventType;
use App\Enums\TicketStatus as Status;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Tickets\Events\TicketAssigned;
use App\Tickets\Events\TicketCancelled;
use App\Tickets\Events\TicketClosed;
use App\Tickets\Events\TicketDomainEvent;
use App\Tickets\Events\TicketPutOnHold;
use App\Tickets\Events\TicketReopened;
use App\Tickets\Events\TicketResolved;
use App\Tickets\Events\TicketResumed;
use App\Tickets\Events\TicketReturnedToPool;
use App\Tickets\Events\TicketStarted;
use App\Tickets\InvalidTicketTransition;
use App\Tickets\TicketActor;
use App\Tickets\TicketTransitions;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

/**
 * A ticket sitting in the status the passage starts from.
 */
function ticketIn(Status $status): Ticket
{
    $factory = Ticket::factory();

    return (match ($status) {
        Status::Nuovo => $factory->nuovo(),
        Status::Assegnato => $factory->assegnato(),
        Status::InLavorazione => $factory->inLavorazione(),
        Status::InAttesa => $factory->inAttesa(),
        Status::Risolto => $factory->risolto(),
        Status::Chiuso => $factory->chiuso(),
        Status::Annullato => $factory->annullato(),
    })->create();
}

/*
 * Every arrow of §4 with the event it emits, written out by hand: thirteen
 * passages and nine names, because three arrows land on `annullato` and two on
 * `risolto` and `in_attesa` — what a transition means is the pair it goes
 * between, not the status it arrives at.
 */
dataset('every transition of the lifecycle', [
    'assignment' => [Status::Nuovo, Status::Assegnato, TicketAssigned::class, TicketEventType::Assegnato],
    'cancelled while new' => [Status::Nuovo, Status::Annullato, TicketCancelled::class, TicketEventType::Annullato],
    'taken up' => [Status::Assegnato, Status::InLavorazione, TicketStarted::class, TicketEventType::PresoInCarico],
    'put on hold before starting' => [Status::Assegnato, Status::InAttesa, TicketPutOnHold::class, TicketEventType::MessoInAttesa],
    'back to the pool' => [Status::Assegnato, Status::Nuovo, TicketReturnedToPool::class, TicketEventType::RimessoNelPool],
    'cancelled while assigned' => [Status::Assegnato, Status::Annullato, TicketCancelled::class, TicketEventType::Annullato],
    'put on hold while working' => [Status::InLavorazione, Status::InAttesa, TicketPutOnHold::class, TicketEventType::MessoInAttesa],
    'resolved from work' => [Status::InLavorazione, Status::Risolto, TicketResolved::class, TicketEventType::Risolto],
    'cancelled while working' => [Status::InLavorazione, Status::Annullato, TicketCancelled::class, TicketEventType::Annullato],
    'requester replied' => [Status::InAttesa, Status::InLavorazione, TicketResumed::class, TicketEventType::Ripreso],
    'resolved from hold' => [Status::InAttesa, Status::Risolto, TicketResolved::class, TicketEventType::Risolto],
    'closed' => [Status::Risolto, Status::Chiuso, TicketClosed::class, TicketEventType::Chiuso],
    'reopened' => [Status::Risolto, Status::InLavorazione, TicketReopened::class, TicketEventType::Riaperto],
]);

test('every transition emits the event of its own passage', function (Status $from, Status $to, string $eventClass) {
    // The ticket is created before the fake: faking every event would take the
    // model events with it, and the reference is drawn on `creating`.
    $ticket = ticketIn($from);

    Event::fake();

    TicketTransitions::apply($ticket, $to, TicketActor::system());

    Event::assertDispatched($eventClass, function (TicketDomainEvent $event) use ($ticket, $from, $to): bool {
        return $event->ticket->is($ticket)
            && $event->from === $from
            && $event->to === $to;
    });
})->with('every transition of the lifecycle');

test('every transition leaves one line in the audit trail', function (Status $from, Status $to, string $eventClass, TicketEventType $type) {
    $ticket = ticketIn($from);
    $agent = User::factory()->agent()->create();

    TicketTransitions::apply($ticket, $to, TicketActor::user($agent));

    assertDatabaseCount('ticket_events', 1);
    assertDatabaseHas('ticket_events', [
        'ticket_id' => $ticket->id,
        'type' => $type->value,
        'actor_type' => $agent->getMorphClass(),
        'actor_id' => $agent->id,
        'actor_kind' => TicketActorType::Utente->value,
    ]);

    expect(TicketEvent::firstOrFail()->payload)
        ->toBe(['from' => $from->value, 'to' => $to->value]);
})->with('every transition of the lifecycle');

test('a transition writes the new status on the ticket', function (Status $from, Status $to) {
    $ticket = ticketIn($from);

    TicketTransitions::apply($ticket, $to, TicketActor::system());

    expect($ticket->status)->toBe($to)
        ->and($ticket->fresh()->status)->toBe($to);
})->with('every transition of the lifecycle');

/*
 * The refusal of step 15 must keep holding now that a passage has side effects:
 * a transition the lifecycle does not admit leaves no trace at all.
 */
test('a refused passage moves nothing and records nothing', function () {
    $ticket = ticketIn(Status::Chiuso);

    // Any domain event getting out would replace the refusal in the assertion
    // below, which is exactly the failure worth hearing about.
    Event::listen(TicketDomainEvent::class, function (): void {
        throw new RuntimeException('a refused passage announced itself');
    });

    expect(fn () => TicketTransitions::apply($ticket, Status::InLavorazione, TicketActor::system()))
        ->toThrow(InvalidTicketTransition::class);

    expect($ticket->fresh()->status)->toBe(Status::Chiuso);
    assertDatabaseCount('ticket_events', 0);
});

/**
 * The trail is not a report of the transition, it is part of it: a status that
 * moves while the line that explains it is missing is worse than neither.
 */
test('the trail and the new status stand or fall together', function () {
    $ticket = ticketIn(Status::Nuovo);

    Event::listen(TicketDomainEvent::class, function (): void {
        throw new RuntimeException('the trail could not be written');
    });

    expect(fn () => TicketTransitions::apply($ticket, Status::Assegnato, TicketActor::system()))
        ->toThrow(RuntimeException::class);

    expect($ticket->fresh()->status)->toBe(Status::Nuovo);
    assertDatabaseCount('ticket_events', 0);
});

test('an actor without a record is recorded by its kind', function (TicketActor $actor, TicketActorType $kind) {
    $ticket = ticketIn(Status::InAttesa);

    TicketTransitions::apply($ticket, Status::Risolto, $actor);

    assertDatabaseHas('ticket_events', [
        'ticket_id' => $ticket->id,
        'actor_type' => null,
        'actor_id' => null,
        'actor_kind' => $kind->value,
    ]);
})->with([
    'the automatic closing job' => fn () => [TicketActor::system(), TicketActorType::Sistema],
    'the triage' => fn () => [TicketActor::ai(), TicketActorType::Ai],
]);

/**
 * The trail reads back as the history of the ticket, in the order it happened.
 */
test('the trail of a ticket taken up, put on hold and resolved reads in order', function () {
    $ticket = ticketIn(Status::Nuovo);
    $agent = User::factory()->agent()->create();
    $actor = TicketActor::user($agent);

    TicketTransitions::apply($ticket, Status::Assegnato, $actor);
    TicketTransitions::apply($ticket, Status::InLavorazione, $actor);
    TicketTransitions::apply($ticket, Status::InAttesa, $actor);
    TicketTransitions::apply($ticket, Status::Risolto, $actor);

    expect($ticket->events->pluck('type')->all())->toBe([
        TicketEventType::Assegnato,
        TicketEventType::PresoInCarico,
        TicketEventType::MessoInAttesa,
        TicketEventType::Risolto,
    ]);
});

/**
 * A single listener answers for the whole vocabulary: it is registered on the
 * interface, so an event added later is recorded without anybody remembering to
 * wire it up.
 */
test('the audit listener answers for the interface and not for one event at a time', function () {
    expect(Event::hasListeners(TicketDomainEvent::class))->toBeTrue();
});
