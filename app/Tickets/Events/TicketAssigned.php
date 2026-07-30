<?php

namespace App\Tickets\Events;

use App\Enums\TicketEventType;

/**
 * `nuovo` → `assegnato`: the ticket left the pool and now has somebody
 * answering for it. The only arrow that moves a ticket out of the intake, and
 * where the notification to the agent of step 38 hangs.
 */
class TicketAssigned extends TicketTransitionEvent
{
    public function type(): TicketEventType
    {
        return TicketEventType::Assegnato;
    }
}
