<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use App\Models\User;

/**
 * A message to append to a thread: which ticket, who is writing, what they
 * wrote and whether the requester is meant to read it.
 *
 * One DTO for the reply and for the internal note, because they are one fact
 * with a flag on it (§4) and not two: a separate shape for the note would be a
 * second way of writing to the same thread, and the day the note grows an
 * attachment the two would have to be kept in step.
 */
class NewReply
{
    public function __construct(
        public readonly Ticket $ticket,
        public readonly User $author,
        public readonly string $body,
        /**
         * A note the team keeps to itself. Public unless the caller says
         * otherwise: the portal and the inbound email have no way to write an
         * internal note, so the default is the one they need.
         */
        public readonly bool $isInternal = false,
        /**
         * The id of the email this reply came in from, when it arrived
         * threaded onto an existing ticket (step 29) — what a later reply's
         * `In-Reply-To` will match against. Null for everything written
         * inside the application.
         */
        public readonly ?string $externalMessageId = null,
        /**
         * The files that came in with this reply, already on disk (step 30).
         * They hang from this message and not from the ticket's first one:
         * every message can carry its own.
         *
         * @var list<NewAttachment>
         */
        public readonly array $attachments = [],
    ) {}
}
