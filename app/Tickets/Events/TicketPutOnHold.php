<?php

namespace App\Tickets\Events;

use App\Enums\TicketEventType;

/**
 * `assegnato` or `in_lavorazione` → `in_attesa`: the ball is with the
 * requester. The clock of the automatic closing of step 42 starts here, which
 * is why the fact is recorded rather than inferred from the status.
 */
class TicketPutOnHold extends TicketTransitionEvent
{
    public function type(): TicketEventType
    {
        return TicketEventType::MessoInAttesa;
    }
}
