<?php

namespace App\Actions\Tickets;

use App\Enums\TicketChannel;
use App\Models\Ticket;
use App\Models\User;

/**
 * A requester answering their own ticket, from the portal or from a threaded
 * inbound email — the fact is the same regardless of the channel it arrived
 * on: which ticket, who is writing and what they wrote.
 *
 * What that reply does to the ticket — resume it, follow it up, or simply
 * append to it — is {@see ReplyFromRequester}'s decision, not the caller's:
 * it depends on the status the ticket is in, not on which channel is asking.
 */
class RequesterReply
{
    public function __construct(
        public readonly Ticket $ticket,
        public readonly User $requester,
        public readonly string $body,
        /**
         * What the follow-up of a closed ticket is born as (§3), since the
         * reply that opens it is not necessarily the channel the ticket
         * itself first came in on.
         */
        public readonly TicketChannel $channel = TicketChannel::Web,
        /**
         * The id of the email this reply came in from, when the channel is
         * email — what a future reply's `In-Reply-To` threads onto. Null for
         * the portal, which writes to the thread directly and has no email
         * of its own.
         */
        public readonly ?string $externalMessageId = null,
        /**
         * The files that came in with the reply, already on disk (step 30).
         *
         * @var list<NewAttachment>
         */
        public readonly array $attachments = [],
    ) {}
}
