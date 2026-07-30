<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use App\Models\User;
use App\Tickets\TicketActor;

/**
 * A request to put a ticket in somebody's hands: which ticket, who gets it, and
 * who is asking.
 *
 * The actor travels with the request because it is not always the agent
 * receiving the ticket — an admin assigns other people's work, and the trail
 * has to read back as who decided, not as who was decided about.
 */
class TicketAssignment
{
    public function __construct(
        public readonly Ticket $ticket,
        public readonly User $assignee,
        public readonly TicketActor $actor,
    ) {}
}
