<?php

use App\Actions\Tickets\NewAttachment;
use App\Actions\Tickets\NewReply;
use App\Actions\Tickets\ReplyToTicket;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;

use function Pest\Laravel\assertDatabaseCount;

/**
 * Run the use case the way the console and the portal will: resolved from the
 * container and handed a DTO.
 *
 * @param  list<NewAttachment>  $attachments
 */
function replyToTicket(
    Ticket $ticket,
    User $author,
    string $body,
    bool $isInternal = false,
    ?string $externalMessageId = null,
    array $attachments = [],
): TicketMessage {
    return app(ReplyToTicket::class)(new NewReply(
        ticket: $ticket,
        author: $author,
        body: $body,
        isInternal: $isInternal,
        externalMessageId: $externalMessageId,
        attachments: $attachments,
    ));
}

test('a reply lands in the thread of its ticket', function () {
    $ticket = Ticket::factory()->inLavorazione()->create();

    $message = replyToTicket(
        $ticket,
        $ticket->assignee,
        'Abbiamo riavviato la coda di stampa, provi di nuovo.',
    );

    expect($message->exists)->toBeTrue()
        ->and($message->ticket_id)->toBe($ticket->id)
        ->and($message->author_id)->toBe($ticket->assignee_id)
        ->and($message->body)->toBe('Abbiamo riavviato la coda di stampa, provi di nuovo.')
        ->and($message->is_internal)->toBeFalse()
        ->and($ticket->messages()->count())->toBe(1);
});

/*
 * The same use case writes both: a note is a message the requester never sees,
 * not a second way of writing to the thread.
 */
test('an internal note lands in the thread marked as internal', function () {
    $ticket = Ticket::factory()->inLavorazione()->create();

    $message = replyToTicket(
        $ticket,
        $ticket->assignee,
        'Il cliente ha già aperto due ticket sullo stesso problema.',
        isInternal: true,
    );

    expect($message->is_internal)->toBeTrue()
        ->and($message->fresh()->is_internal)->toBeTrue();
});

/*
 * The metric of §5, written by the Action that causes the fact and never
 * recomputed afterwards from the events.
 */
test('the first public reply of an operator starts the first response metric', function () {
    $ticket = Ticket::factory()->inLavorazione()->create();

    $message = replyToTicket($ticket, $ticket->assignee, 'Ci stiamo lavorando.');

    expect($ticket->fresh()->first_response_at)->not->toBeNull()
        ->and($ticket->fresh()->first_response_at->equalTo($message->created_at))->toBeTrue();
});

test('an admin answering counts as the team answering', function () {
    $ticket = Ticket::factory()->nuovo()->create();
    $admin = User::factory()->admin()->create();

    replyToTicket($ticket, $admin, 'Me ne occupo io.');

    expect($ticket->fresh()->first_response_at)->not->toBeNull();
});

/*
 * A note is written for the team and the requester never reads it: counting it
 * would report a ticket as answered while nobody has answered.
 */
test('an internal note is not a response to anybody', function () {
    $ticket = Ticket::factory()->inLavorazione()->create();

    replyToTicket($ticket, $ticket->assignee, 'Da verificare con il fornitore.', isInternal: true);

    expect($ticket->fresh()->first_response_at)->toBeNull();
});

/*
 * The metric measures how long the team takes to answer: the requester adding
 * to their own request is not the team answering it.
 */
test('the requester writing again is not a first response', function () {
    $ticket = Ticket::factory()->nuovo()->create();

    replyToTicket($ticket, $ticket->requester, 'Aggiungo che succede solo dal mio portatile.');

    expect($ticket->fresh()->first_response_at)->toBeNull();
});

/*
 * First means first: the metric is written once, and every reply afterwards
 * leaves it where it is.
 */
test('a later reply does not rewrite the first response', function () {
    $ticket = Ticket::factory()->inAttesa()->create();
    $firstResponse = $ticket->first_response_at;

    replyToTicket($ticket, $ticket->assignee, 'Le confermo che il problema è risolto.');

    expect($ticket->fresh()->first_response_at->equalTo($firstResponse))->toBeTrue();
});

/*
 * A reply is not a passage: the lifecycle is moved by the Actions of steps 20
 * and 27, and answering a ticket must not move it on its own.
 */
test('a reply leaves the ticket where it is and writes nothing in the trail', function () {
    $ticket = Ticket::factory()->inLavorazione()->create();

    replyToTicket($ticket, $ticket->assignee, 'Le rispondo appena ho novità.');

    expect($ticket->fresh()->status)->toBe(TicketStatus::InLavorazione);

    assertDatabaseCount('ticket_events', 0);
});

/*
 * The reply and the metric it starts are one fact: a first response recorded on
 * a ticket whose message is missing is a metric measuring nothing.
 */
test('the reply and the metric it starts stand or fall together', function () {
    $ticket = Ticket::factory()->inLavorazione()->create();

    Ticket::updating(function (): void {
        throw new RuntimeException('the metric could not be written');
    });

    expect(fn () => replyToTicket($ticket, $ticket->assignee, 'Risposta che non deve sopravvivere.'))
        ->toThrow(RuntimeException::class);

    assertDatabaseCount('ticket_messages', 0);

    expect($ticket->fresh()->first_response_at)->toBeNull();
});

/*
 * The id of a threaded inbound email is what a later reply's `In-Reply-To`
 * will match against (step 29).
 */
test('a reply threaded from an email carries its id', function () {
    $ticket = Ticket::factory()->inLavorazione()->create();

    $message = replyToTicket(
        $ticket,
        $ticket->requester,
        'Aggiungo un dettaglio via email.',
        externalMessageId: '<abc123@mail.example.com>',
    );

    expect($message->external_message_id)->toBe('<abc123@mail.example.com>');
});

/*
 * A reply carries its own attachments (step 30) — they hang from the message
 * that came in with them, not from the ticket's first one.
 */
test('a reply carries the attachments it came in with', function () {
    $ticket = Ticket::factory()->inLavorazione()->create();

    $message = replyToTicket(
        $ticket,
        $ticket->requester,
        'Allego uno screenshot.',
        attachments: [
            new NewAttachment(
                disk: 'attachments',
                path: 'attachments/example.png',
                originalName: 'errore.png',
                mimeType: 'image/png',
                size: 1234,
            ),
        ],
    );

    expect($message->attachments)->toHaveCount(1)
        ->and($message->attachments->first()->original_name)->toBe('errore.png');
});
