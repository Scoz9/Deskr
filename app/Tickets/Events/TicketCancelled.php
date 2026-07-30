<?php

namespace App\Tickets\Events;

use App\Enums\TicketEventType;

/**
 * `nuovo`, `assegnato` or `in_lavorazione` → `annullato`: spam or an invalid
 * request, out of the way without ever being assigned to a real agent to get
 * rid of it. Terminal, and deliberately not a resolution: a cancelled ticket
 * must not enter the resolution time.
 */
class TicketCancelled extends TicketTransitionEvent
{
    public function type(): TicketEventType
    {
        return TicketEventType::Annullato;
    }
}
