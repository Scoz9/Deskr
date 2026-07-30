<?php

namespace App\Tickets\Events;

use App\Enums\TicketEventType;

/**
 * `risolto` → `in_lavorazione`: the resolution did not hold. There is no
 * `riaperto` status (§4) — it had one exit and no behaviour of its own — so
 * this event is the only place a reopening exists as a fact, and what step 20
 * counts on to increment `reopen_count`.
 */
class TicketReopened extends TicketTransitionEvent
{
    public function type(): TicketEventType
    {
        return TicketEventType::Riaperto;
    }
}
