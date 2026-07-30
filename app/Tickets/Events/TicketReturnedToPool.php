<?php

namespace App\Tickets\Events;

use App\Enums\TicketEventType;

/**
 * `assegnato` → `nuovo`: the agent gave the ticket back. It is the one arrow
 * that walks the lifecycle backwards, and it exists so that a ticket taken by
 * mistake has a way out that is not a cancellation.
 */
class TicketReturnedToPool extends TicketTransitionEvent
{
    public function type(): TicketEventType
    {
        return TicketEventType::RimessoNelPool;
    }
}
