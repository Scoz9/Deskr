<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use App\Models\User;

/**
 * A requester answering their own ticket from the portal: which ticket, who
 * is writing and what they wrote.
 *
 * What that reply does to the ticket — resume it, follow it up, or simply
 * append to it — is {@see ReplyFromPortal}'s decision, not the caller's: it
 * depends on the status the ticket is in, not on anything the portal knows.
 */
class PortalReply
{
    public function __construct(
        public readonly Ticket $ticket,
        public readonly User $requester,
        public readonly string $body,
    ) {}
}
