<?php

namespace App\Tickets\Events;

use App\Enums\TicketEventType;
use App\Models\Ticket;
use App\Tickets\TicketActor;

/**
 * Something happened to a ticket that the audit trail has to remember.
 *
 * The trail listens on this interface and not on the events one by one: the
 * dispatcher resolves listeners registered on an interface, so an event added
 * later is recorded by the fact of implementing this, without anybody
 * remembering to wire it up. It is also what step 18 has to implement to record
 * a reassignment, which is not a transition and still belongs to the trail.
 */
interface TicketDomainEvent
{
    /**
     * The ticket this happened on.
     */
    public Ticket $ticket {
        get;
    }

    /**
     * Who did it — a person, the system or the AI.
     */
    public TicketActor $actor {
        get;
    }

    /**
     * The name this fact is written under in the trail.
     */
    public function type(): TicketEventType;

    /**
     * What the fact carries beyond its name.
     *
     * @return array<string, mixed>|null
     */
    public function payload(): ?array;
}
