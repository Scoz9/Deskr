<?php

use App\Actions\Tickets\AssignTicket;
use App\Actions\Tickets\TicketAssignment;
use App\Enums\TicketActorType;
use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Tickets\Events\TicketAssigned;
use App\Tickets\Events\TicketDomainEvent;
use App\Tickets\Events\TicketReassigned;
use App\Tickets\TicketActor;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

/**
 * A ticket sitting in the status the assignment starts from.
 */
function ticketToAssign(TicketStatus $status): Ticket
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
function assignTicket(Ticket $ticket, User $assignee, ?TicketActor $actor = null): Ticket
{
    return app(AssignTicket::class)(new TicketAssignment(
        ticket: $ticket,
        assignee: $assignee,
        actor: $actor ?? TicketActor::user($assignee),
    ));
}

/*
 * Every status a ticket can already be in when somebody assigns it. `nuovo` is
 * missing on purpose: it is the one status the assignment moves, and it has its
 * own tests.
 */
dataset('every status a ticket is already out of the pool in', [
    'assigned' => [TicketStatus::Assegnato],
    'being worked on' => [TicketStatus::InLavorazione],
    'waiting on the requester' => [TicketStatus::InAttesa],
    'resolved' => [TicketStatus::Risolto],
    'closed' => [TicketStatus::Chiuso],
    'cancelled' => [TicketStatus::Annullato],
]);

test('a ticket taken out of the pool moves to assegnato', function () {
    $ticket = ticketToAssign(TicketStatus::Nuovo);
    $agent = User::factory()->agent()->create();

    assignTicket($ticket, $agent);

    expect($ticket->status)->toBe(TicketStatus::Assegnato)
        ->and($ticket->assignee_id)->toBe($agent->id)
        ->and($ticket->fresh()->status)->toBe(TicketStatus::Assegnato)
        ->and($ticket->fresh()->assignee_id)->toBe($agent->id);
});

/*
 * One passage, one line: the assignment out of the pool is the transition of
 * §4, so it is `TicketAssigned` that announces it and nothing else — a
 * reassignment event next to it would say twice a thing that happened once.
 */
test('taking a ticket out of the pool announces itself as the transition it is', function () {
    $ticket = ticketToAssign(TicketStatus::Nuovo);
    $agent = User::factory()->agent()->create();

    Event::fake();

    assignTicket($ticket, $agent);

    Event::assertDispatched(TicketAssigned::class);
    Event::assertNotDispatched(TicketReassigned::class);
});

test('the assignment out of the pool leaves one line in the trail', function () {
    $ticket = ticketToAssign(TicketStatus::Nuovo);
    $agent = User::factory()->agent()->create();

    assignTicket($ticket, $agent, TicketActor::user($agent));

    assertDatabaseCount('ticket_events', 1);
    assertDatabaseHas('ticket_events', [
        'ticket_id' => $ticket->id,
        'type' => TicketEventType::Assegnato->value,
        'actor_type' => $agent->getMorphClass(),
        'actor_id' => $agent->id,
        'actor_kind' => TicketActorType::Utente->value,
    ]);
});

/*
 * The assignee is an attribute and not a state (§3): handing a ticket to
 * somebody else must not walk it back through the lifecycle and falsify the
 * metrics of a ticket that is exactly where it was.
 */
test('a ticket already out of the pool only changes hands', function (TicketStatus $status) {
    $ticket = ticketToAssign($status);
    $other = User::factory()->agent()->create();

    assignTicket($ticket, $other);

    expect($ticket->fresh()->status)->toBe($status)
        ->and($ticket->fresh()->assignee_id)->toBe($other->id);
})->with('every status a ticket is already out of the pool in');

test('a reassignment announces itself and is not a transition', function (TicketStatus $status) {
    $ticket = ticketToAssign($status);
    $previous = $ticket->assignee_id;
    $other = User::factory()->agent()->create();

    assignTicket($ticket, $other);

    assertDatabaseCount('ticket_events', 1);
    assertDatabaseHas('ticket_events', [
        'ticket_id' => $ticket->id,
        'type' => TicketEventType::Riassegnato->value,
    ]);

    expect(TicketEvent::firstOrFail()->payload)
        ->toBe(['from' => $previous, 'to' => $other->id]);
})->with('every status a ticket is already out of the pool in');

/*
 * Standing still is not a passage, and it is not a reassignment either: giving
 * a ticket to whoever already has it moves nothing, so it announces nothing.
 */
test('handing a ticket to whoever already has it changes nothing and records nothing', function () {
    $ticket = ticketToAssign(TicketStatus::InLavorazione);
    $assignee = $ticket->assignee;

    Event::listen(TicketDomainEvent::class, function (): void {
        throw new RuntimeException('a ticket that did not move announced itself');
    });

    assignTicket($ticket, $assignee);

    expect($ticket->fresh()->assignee_id)->toBe($assignee->id)
        ->and($ticket->fresh()->status)->toBe(TicketStatus::InLavorazione);

    assertDatabaseCount('ticket_events', 0);
});

/*
 * A ticket cannot change hands while the line saying who has it now is missing:
 * the trail is part of the assignment, not a report of it.
 */
test('the new assignee and its line in the trail stand or fall together', function (TicketStatus $status) {
    $ticket = ticketToAssign($status);
    $previous = $ticket->assignee_id;
    $other = User::factory()->agent()->create();

    Event::listen(TicketDomainEvent::class, function (): void {
        throw new RuntimeException('the trail could not be written');
    });

    expect(fn () => assignTicket($ticket, $other))->toThrow(RuntimeException::class);

    expect($ticket->fresh()->assignee_id)->toBe($previous)
        ->and($ticket->fresh()->status)->toBe($status);

    assertDatabaseCount('ticket_events', 0);
})->with([
    'out of the pool' => [TicketStatus::Nuovo],
    'already assigned' => [TicketStatus::Assegnato],
]);

/*
 * The trail reads back as the history of the ticket: who took it, and who it
 * was handed to afterwards.
 */
test('the trail of a ticket taken and then handed over reads in order', function () {
    $ticket = ticketToAssign(TicketStatus::Nuovo);
    $first = User::factory()->agent()->create();
    $second = User::factory()->agent()->create();

    assignTicket($ticket, $first);
    assignTicket($ticket, $second);

    expect($ticket->events->pluck('type')->all())->toBe([
        TicketEventType::Assegnato,
        TicketEventType::Riassegnato,
    ]);
});

test('the actor of an assignment is not necessarily the agent receiving it', function () {
    $ticket = ticketToAssign(TicketStatus::Nuovo);
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();

    assignTicket($ticket, $agent, TicketActor::user($admin));

    assertDatabaseHas('ticket_events', [
        'ticket_id' => $ticket->id,
        'actor_id' => $admin->id,
        'actor_kind' => TicketActorType::Utente->value,
    ]);

    expect($ticket->fresh()->assignee_id)->toBe($agent->id);
});
