<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Tickets\TicketActor;

/**
 * A request to move a ticket through its lifecycle: which ticket, where it is
 * going and who is moving it.
 *
 * The destination is a status and not a verb. Resolving, closing, reopening,
 * putting on hold and cancelling are the five the console offers, but what
 * tells them apart is already written in the table of §4 — a verb per case here
 * would be a second vocabulary of the passages, to be kept in step with the
 * first one at every arrow added.
 */
class TicketTransitionRequest
{
    public function __construct(
        public readonly Ticket $ticket,
        public readonly TicketStatus $status,
        /**
         * Not always a person: the automatic closings of step 42 move tickets
         * with nobody watching, and the trail has to read back as the system
         * having decided.
         */
        public readonly TicketActor $actor,
    ) {}
}
