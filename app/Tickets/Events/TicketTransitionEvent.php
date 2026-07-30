<?php

namespace App\Tickets\Events;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Tickets\TicketActor;

/**
 * The passage of a ticket from one status to another, already made: the event
 * is dispatched after the new status is written, so a listener that reads the
 * ticket reads it as it now is.
 *
 * Every arrow of §4 has its own subclass. One generic event would force each
 * listener to reconstruct from the pair what the passage meant — and the three
 * arrows that arrive at `in_lavorazione` do not mean the same thing at all:
 * one is a ticket being taken up, one a requester answering, one a reopening.
 */
abstract class TicketTransitionEvent implements TicketDomainEvent
{
    public function __construct(
        public readonly Ticket $ticket,
        public readonly TicketStatus $from,
        public readonly TicketStatus $to,
        public readonly TicketActor $actor,
    ) {}

    /**
     * Both ends of the passage. The trail is read years later, when the current
     * status of the ticket says nothing about where it came from.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'from' => $this->from->value,
            'to' => $this->to->value,
        ];
    }
}
