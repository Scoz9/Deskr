<?php

namespace App\Tickets\Events;

use App\Enums\TicketEventType;

/**
 * `in_lavorazione` or `in_attesa` → `risolto`: the team is done. The requester
 * is told at step 37 and has seven days to disagree, which is what makes this
 * the event the resolution time is measured against.
 */
class TicketResolved extends TicketTransitionEvent
{
    public function type(): TicketEventType
    {
        return TicketEventType::Risolto;
    }
}
