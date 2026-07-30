<?php

namespace App\Tickets\Events;

use App\Enums\TicketEventType;

/**
 * `in_attesa` → `in_lavorazione`: the requester answered and the ball is back
 * with the team. Told apart from a reopening, which lands on the same status
 * from a ticket that had already been resolved.
 */
class TicketResumed extends TicketTransitionEvent
{
    public function type(): TicketEventType
    {
        return TicketEventType::Ripreso;
    }
}
