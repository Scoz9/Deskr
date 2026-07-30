<?php

namespace App\Tickets\Events;

use App\Enums\TicketEventType;

/**
 * `risolto` → `chiuso`: terminal. A reply on a closed ticket opens a linked one
 * (§3), so nothing comes back through here.
 */
class TicketClosed extends TicketTransitionEvent
{
    public function type(): TicketEventType
    {
        return TicketEventType::Chiuso;
    }
}
