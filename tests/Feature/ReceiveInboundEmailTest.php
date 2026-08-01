<?php

use App\Actions\Tickets\InboundEmail;
use App\Actions\Tickets\ReceiveInboundEmail;
use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Database\Seeders\RoleSeeder;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/**
 * An email shaped the way the Postmark adapter hands one to the domain.
 */
function inboundEmail(array $overrides = []): InboundEmail
{
    return new InboundEmail(
        fromEmail: $overrides['fromEmail'] ?? 'anna.rossi@example.com',
        fromName: $overrides['fromName'] ?? 'Anna Rossi',
        subject: $overrides['subject'] ?? 'La stampante non risponde',
        body: $overrides['body'] ?? 'Da stamattina la stampante del secondo piano non stampa.',
        externalMessageId: array_key_exists('externalMessageId', $overrides) ? $overrides['externalMessageId'] : '<msg-1@mail.example.com>',
        inReplyTo: $overrides['inReplyTo'] ?? null,
        references: $overrides['references'] ?? [],
        autoSubmitted: $overrides['autoSubmitted'] ?? false,
    );
}

function receiveInboundEmail(InboundEmail $email): ?Ticket
{
    return app(ReceiveInboundEmail::class)($email);
}

test('a first time email opens a new ticket on the email channel', function () {
    $ticket = receiveInboundEmail(inboundEmail());

    expect($ticket)->not->toBeNull()
        ->and($ticket->channel)->toBe(TicketChannel::Email)
        ->and($ticket->status)->toBe(TicketStatus::Nuovo);
});

/*
 * The reference in the subject is the threading key (§4, decided when the
 * confirmation email of step 25 put it in the subject line).
 */
test('a reply carrying the reference in its subject threads onto that ticket', function () {
    $requester = User::factory()->requester()->create(['email' => 'anna.rossi@example.com']);
    $ticket = Ticket::factory()->inAttesa()->for($requester, 'requester')->create();

    $result = receiveInboundEmail(inboundEmail([
        'subject' => 'Re: ['.$ticket->reference.'] La stampante non risponde',
        'body' => 'Succede ancora.',
    ]));

    expect($result->id)->toBe($ticket->id)
        ->and($result->status)->toBe(TicketStatus::InLavorazione);

    assertDatabaseHas('ticket_messages', [
        'ticket_id' => $ticket->id,
        'body' => 'Succede ancora.',
        'author_id' => $requester->id,
    ]);
});

/*
 * `In-Reply-To` is the second key (§5), for a client that has stripped the
 * reference out of a reply's subject.
 */
test('a reply whose In-Reply-To matches a known message threads onto its ticket', function () {
    $requester = User::factory()->requester()->create(['email' => 'anna.rossi@example.com']);
    $ticket = Ticket::factory()->inAttesa()->for($requester, 'requester')->create();
    TicketMessage::factory()->for($ticket)->create(['external_message_id' => '<original@mail.example.com>']);

    $result = receiveInboundEmail(inboundEmail([
        'subject' => 'Re: la mia richiesta',
        'inReplyTo' => '<original@mail.example.com>',
    ]));

    expect($result->id)->toBe($ticket->id);
});

test('a reply resolved only through References still threads', function () {
    $requester = User::factory()->requester()->create(['email' => 'anna.rossi@example.com']);
    $ticket = Ticket::factory()->inAttesa()->for($requester, 'requester')->create();
    TicketMessage::factory()->for($ticket)->create(['external_message_id' => '<original@mail.example.com>']);

    $result = receiveInboundEmail(inboundEmail([
        'subject' => 'Re: la mia richiesta',
        'inReplyTo' => '<something-else@mail.example.com>',
        'references' => ['<something-else@mail.example.com>', '<original@mail.example.com>'],
    ]));

    expect($result->id)->toBe($ticket->id);
});

/*
 * A closed ticket does not reopen from an email either (§3): the same rule
 * ReplyFromRequester already applies to the portal.
 */
test('a threaded reply to a closed ticket opens a follow-up instead of reopening it', function () {
    $requester = User::factory()->requester()->create(['email' => 'anna.rossi@example.com']);
    $ticket = Ticket::factory()->chiuso()->for($requester, 'requester')->create();

    $followUp = receiveInboundEmail(inboundEmail([
        'subject' => 'Re: ['.$ticket->reference.'] La stampante non risponde',
    ]));

    expect($followUp->id)->not->toBe($ticket->id)
        ->and($followUp->parent_ticket_id)->toBe($ticket->id)
        ->and($followUp->channel)->toBe(TicketChannel::Email);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Chiuso);
});

/*
 * The policy on an unknown sender (§5): a reference in the subject is
 * something anybody can type. Threading only holds if the address writing
 * now is the address the ticket already belongs to.
 */
test('a stranger quoting somebody else\'s reference opens a new ticket instead of theirs', function () {
    $owner = User::factory()->requester()->create(['email' => 'anna.rossi@example.com']);
    $ticket = Ticket::factory()->inAttesa()->for($owner, 'requester')->create();

    $result = receiveInboundEmail(inboundEmail([
        'fromEmail' => 'mario.bianchi@example.com',
        'fromName' => 'Mario Bianchi',
        'subject' => 'Re: ['.$ticket->reference.'] intercettata',
    ]));

    expect($result->id)->not->toBe($ticket->id)
        ->and($result->requester->email)->toBe('mario.bianchi@example.com');

    expect($ticket->fresh()->status)->toBe(TicketStatus::InAttesa);
});

/*
 * RFC 3834: an autoresponder says so itself. Two of them answering each
 * other is the loop this stops before it reaches the domain (§5).
 */
test('an autosubmitted email is dropped, not turned into a ticket', function () {
    $result = receiveInboundEmail(inboundEmail(['autoSubmitted' => true]));

    expect($result)->toBeNull();

    assertDatabaseCount('tickets', 0);
});

/*
 * The column is unique for this reason (§4): a provider delivering the same
 * webhook twice must not open the same ticket, or append the same message,
 * twice.
 */
test('the same email delivered twice is received once', function () {
    $first = receiveInboundEmail(inboundEmail(['externalMessageId' => '<same@mail.example.com>']));
    $second = receiveInboundEmail(inboundEmail(['externalMessageId' => '<same@mail.example.com>']));

    expect($second)->toBeNull();

    assertDatabaseCount('tickets', 1);
    expect($first->messages)->toHaveCount(1);
});

test('an email with no id to deduplicate on is still received', function () {
    $first = receiveInboundEmail(inboundEmail(['externalMessageId' => null]));
    $second = receiveInboundEmail(inboundEmail(['externalMessageId' => null]));

    expect($first)->not->toBeNull()
        ->and($second)->not->toBeNull();

    assertDatabaseCount('tickets', 2);
});

/*
 * Two autorisponditori that dialogue generate thousands of tickets in a
 * night without a cap (§5): a person never writes this fast.
 */
test('a sender past the rate limit is dropped', function () {
    foreach (range(1, ReceiveInboundEmail::MESSAGES_PER_SENDER_PER_MINUTE) as $attempt) {
        receiveInboundEmail(inboundEmail(['externalMessageId' => "<msg-{$attempt}@mail.example.com>"]));
    }

    $result = receiveInboundEmail(inboundEmail(['externalMessageId' => '<one-too-many@mail.example.com>']));

    expect($result)->toBeNull();

    assertDatabaseCount('tickets', ReceiveInboundEmail::MESSAGES_PER_SENDER_PER_MINUTE);
});
