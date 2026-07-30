<?php

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('the factory persists a public message written by the requester', function () {
    $message = TicketMessage::factory()->create();

    expect($message->body)->toBeString()->not->toBeEmpty()
        ->and($message->is_internal)->toBeFalse()
        ->and($message->external_message_id)->toBeNull()
        ->and($message->author_id)->toBe($message->ticket->requester_id);

    $this->assertDatabaseHas('ticket_messages', [
        'id' => $message->id,
        'ticket_id' => $message->ticket_id,
        'is_internal' => false,
    ]);
});

test('a message belongs to a ticket and to the person who wrote it', function () {
    $message = TicketMessage::factory()->create();

    expect($message->ticket)->toBeInstanceOf(Ticket::class)
        ->and($message->author)->toBeInstanceOf(User::class);
});

/**
 * The thread is read in the order it was written, whatever order the rows come
 * back from the database in.
 */
test('the thread of a ticket is its messages oldest first', function () {
    $ticket = Ticket::factory()->create();

    $second = TicketMessage::factory()->for($ticket)->create(['created_at' => now()->subHour()]);
    $first = TicketMessage::factory()->for($ticket)->create(['created_at' => now()->subHours(3)]);

    expect($ticket->messages->pluck('id')->all())->toBe([$first->id, $second->id]);
});

test('two messages written in the same second keep the order they were written in', function () {
    $ticket = Ticket::factory()->create();
    $writtenAt = now()->subHour();

    $first = TicketMessage::factory()->for($ticket)->create(['created_at' => $writtenAt]);
    $second = TicketMessage::factory()->for($ticket)->create(['created_at' => $writtenAt]);

    expect($ticket->messages->pluck('id')->all())->toBe([$first->id, $second->id]);
});

test('a message cannot exist without a ticket to hang from', function () {
    expect(fn () => TicketMessage::factory()->create(['ticket_id' => null]))
        ->toThrow(QueryException::class);
});

test('a message cannot exist without a body', function () {
    expect(fn () => TicketMessage::factory()->create(['body' => null]))
        ->toThrow(QueryException::class);
});

test('a message cannot exist without an author', function () {
    expect(fn () => TicketMessage::factory()->create(['author_id' => null]))
        ->toThrow(QueryException::class);
});

/**
 * The author is never dropped from under a message: erasing a person anonymizes
 * the user and keeps the thread readable, the same rule the requester of a
 * ticket follows.
 */
test('a message keeps the author who wrote it', function () {
    $message = TicketMessage::factory()->dellOperatore()->create();

    expect(fn () => $message->author->delete())->toThrow(QueryException::class);
});

/**
 * Messages are part of the ticket aggregate and mean nothing without it, so
 * they go where it goes.
 */
test('deleting a ticket takes its messages with it', function () {
    $message = TicketMessage::factory()->create();

    $message->ticket->delete();

    $this->assertDatabaseMissing('ticket_messages', ['id' => $message->id]);
});

test('an internal note is written by an agent and is not visible to the requester', function () {
    $note = TicketMessage::factory()->interna()->create();

    expect($note->is_internal)->toBeTrue()
        ->and($note->author_id)->not->toBe($note->ticket->requester_id);

    $this->assertDatabaseHas('ticket_messages', [
        'id' => $note->id,
        'is_internal' => true,
    ]);
});

test('the same thread holds replies to the requester and notes that stay in the team', function () {
    $ticket = Ticket::factory()->create();

    $reply = TicketMessage::factory()->for($ticket)->dellOperatore()->create();
    $note = TicketMessage::factory()->for($ticket)->interna()->create();

    expect($reply->is_internal)->toBeFalse()
        ->and($note->is_internal)->toBeTrue()
        ->and($ticket->messages)->toHaveCount(2);
});

test('a message written in the application has no external id', function () {
    expect(TicketMessage::factory()->create()->external_message_id)->toBeNull();
});

test('a message that came in by email carries the id of the email it came from', function () {
    $message = TicketMessage::factory()->daEmail()->create();

    expect($message->external_message_id)->toBeString()->not->toBeEmpty()
        ->and($message->is_internal)->toBeFalse();
});

/**
 * A provider that delivers the same webhook twice must not append the same
 * email to the thread twice.
 */
test('the same inbound email cannot be stored twice', function () {
    $message = TicketMessage::factory()->daEmail()->create();

    expect(fn () => TicketMessage::factory()->daEmail()->create([
        'external_message_id' => $message->external_message_id,
    ]))->toThrow(QueryException::class);
});

/**
 * The uniqueness is on the external id, not on its absence: everything written
 * from the console leaves it null.
 */
test('messages written in the application do not collide with each other', function () {
    TicketMessage::factory()->count(3)->create();

    expect(TicketMessage::query()->whereNull('external_message_id')->count())->toBe(3);
});

test('is_internal is a boolean on the model and in the database', function () {
    $note = TicketMessage::factory()->interna()->create();

    $row = DB::table('ticket_messages')->where('id', $note->id)->first();

    expect($note->is_internal)->toBeBool()
        ->and($row?->is_internal)->toBeTrue();
});
