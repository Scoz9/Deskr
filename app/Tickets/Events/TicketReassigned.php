<?php

namespace App\Tickets\Events;

use App\Enums\TicketEventType;
use App\Models\Ticket;
use App\Models\User;
use App\Tickets\TicketActor;

/**
 * The ticket changed hands without moving: the assignee is an attribute and not
 * a state (§3), so handing a ticket to somebody else leaves it exactly where it
 * was in the lifecycle.
 *
 * The first domain event that is not a transition, and the reason the trail
 * listens on {@see TicketDomainEvent} instead of on the nine transition events
 * one by one: this lands in the audit trail by the fact of implementing the
 * interface, with nobody having to wire it up.
 */
class TicketReassigned implements TicketDomainEvent
{
    public function __construct(
        public readonly Ticket $ticket,
        public readonly ?User $from,
        public readonly User $to,
        public readonly TicketActor $actor,
    ) {}

    public function type(): TicketEventType
    {
        return TicketEventType::Riassegnato;
    }

    /**
     * Both ends of the handover. `from` is null when the ticket was in
     * somebody's list without ever having been taken — a cancelled request
     * nobody had picked up — which is a fact worth reading back as it was.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'from' => $this->from?->getKey(),
            'to' => $this->to->getKey(),
        ];
    }
}
