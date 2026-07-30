<?php

namespace App\Tickets\Events;

use App\Enums\TicketEventType;

/**
 * `assegnato` → `in_lavorazione`: work has begun. Not a first response — taking
 * a ticket up is not answering it, and the metric must not say otherwise.
 */
class TicketStarted extends TicketTransitionEvent
{
    public function type(): TicketEventType
    {
        return TicketEventType::PresoInCarico;
    }
}
