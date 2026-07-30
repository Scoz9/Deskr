<?php

use App\Enums\TicketActorType;
use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('the factory persists an event written by a person', function () {
    $event = TicketEvent::factory()->create();

    expect($event->actor_kind)->toBe(TicketActorType::Utente)
        ->and($event->actor)->toBeInstanceOf(User::class)
        ->and($event->type)->toBeInstanceOf(TicketEventType::class);

    $this->assertDatabaseHas('ticket_events', [
        'id' => $event->id,
        'ticket_id' => $event->ticket_id,
        'actor_kind' => TicketActorType::Utente->value,
    ]);
});

test('an event belongs to the ticket it happened on', function () {
    $event = TicketEvent::factory()->create();

    expect($event->ticket)->toBeInstanceOf(Ticket::class);
});

test('an event cannot exist without a ticket to hang from', function () {
    expect(fn () => TicketEvent::factory()->create(['ticket_id' => null]))
        ->toThrow(QueryException::class);
});

test('an event cannot exist without saying what happened', function (string $column) {
    expect(fn () => TicketEvent::factory()->create([$column => null]))
        ->toThrow(QueryException::class);
})->with(['type', 'actor_kind']);

/**
 * The audit trail is part of the ticket: it answers questions about that
 * ticket, and there is nothing left to answer once the ticket is gone.
 */
test('deleting a ticket takes its events with it', function () {
    $event = TicketEvent::factory()->create();

    $event->ticket->delete();

    $this->assertDatabaseMissing('ticket_events', ['id' => $event->id]);
});

/**
 * §4: the actor is not always a person. A job closing a ticket at the end of
 * the wait has no user to point at, and the kind is what keeps the row
 * readable — a null actor with the reason lost would not.
 */
test('an event written by the system has no actor record but says so', function () {
    $event = TicketEvent::factory()->delSistema()->create();

    expect($event->actor_kind)->toBe(TicketActorType::Sistema)
        ->and($event->actor)->toBeNull()
        ->and($event->actor_type)->toBeNull()
        ->and($event->actor_id)->toBeNull();
});

test('an event written by the AI is told apart from one written by the system', function () {
    $event = TicketEvent::factory()->dellAi()->create();

    expect($event->actor_kind)->toBe(TicketActorType::Ai)
        ->and($event->actor)->toBeNull();
});

/**
 * The three kinds live in the same table and are read together: the timeline of
 * a ticket does not care who acted, only that it says who.
 */
test('the timeline of a ticket holds every kind of actor', function () {
    $ticket = Ticket::factory()->create();

    TicketEvent::factory()->for($ticket)->create();
    TicketEvent::factory()->for($ticket)->delSistema()->create();
    TicketEvent::factory()->for($ticket)->dellAi()->create();

    expect($ticket->events)->toHaveCount(3)
        ->and($ticket->events->pluck('actor_kind')->all())
        ->toBe([TicketActorType::Utente, TicketActorType::Sistema, TicketActorType::Ai]);
});

test('the timeline of a ticket is read oldest first', function () {
    $ticket = Ticket::factory()->create();

    $second = TicketEvent::factory()->for($ticket)->create(['created_at' => now()->subHour()]);
    $first = TicketEvent::factory()->for($ticket)->create(['created_at' => now()->subHours(3)]);

    expect($ticket->events->pluck('id')->all())->toBe([$first->id, $second->id]);
});

test('two events written in the same second keep the order they happened in', function () {
    $ticket = Ticket::factory()->create();
    $happenedAt = now()->subHour();

    $first = TicketEvent::factory()->for($ticket)->create(['created_at' => $happenedAt]);
    $second = TicketEvent::factory()->for($ticket)->create(['created_at' => $happenedAt]);

    expect($ticket->events->pluck('id')->all())->toBe([$first->id, $second->id]);
});

/**
 * What the event carries beyond its name: the statuses a transition went
 * between, the agent an assignment landed on. Free-form because which events
 * exist is the step 16 decision, not this one.
 */
test('an event carries the payload of what happened', function () {
    $event = TicketEvent::factory()->create([
        'type' => TicketEventType::Assegnato,
        'payload' => ['from' => TicketStatus::Nuovo->value, 'to' => TicketStatus::Assegnato->value],
    ]);

    expect($event->refresh()->payload)
        ->toBe(['from' => 'nuovo', 'to' => 'assegnato']);
});

test('an event with nothing to add carries no payload', function () {
    $event = TicketEvent::factory()->create(['payload' => null]);

    expect($event->refresh()->payload)->toBeNull();
});

/**
 * An audit row is written once and never touched again: there is no state in
 * which "when it was last modified" means anything.
 */
test('an event is written once and never updated', function () {
    $event = TicketEvent::factory()->create();

    expect($event->created_at)->not->toBeNull()
        ->and($event->updated_at ?? null)->toBeNull()
        ->and(TicketEvent::UPDATED_AT)->toBeNull();

    $columns = DB::getSchemaBuilder()->getColumnListing('ticket_events');

    expect($columns)->toContain('created_at')->not->toContain('updated_at');
});

test('the actor of an event is polimorphic and can be any kind of person the app knows', function () {
    $agent = User::factory()->agent()->create();

    $event = TicketEvent::factory()->create(['actor_type' => $agent->getMorphClass(), 'actor_id' => $agent->id]);

    expect($event->actor)->toBeInstanceOf(User::class)
        ->and($event->actor->id)->toBe($agent->id);
});
