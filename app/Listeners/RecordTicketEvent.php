<?php

namespace App\Listeners;

use App\Models\TicketEvent;
use App\Tickets\Events\TicketDomainEvent;

/**
 * Writes the audit trail. One listener for the whole vocabulary: it takes the
 * interface, so every domain event lands here by the fact of being one.
 *
 * Deliberately not queued, while every notification is (§5). A notification
 * that arrives a minute late is a notification; a trail that arrives a minute
 * late is a ticket whose history has a hole in it for a minute, and one that
 * fails silently is a ticket whose history is wrong for good. It runs inside
 * the transaction of the transition, so either both land or neither does.
 */
class RecordTicketEvent
{
    public function handle(TicketDomainEvent $event): void
    {
        TicketEvent::create([
            'ticket_id' => $event->ticket->getKey(),
            'type' => $event->type(),
            'actor_type' => $event->actor->record?->getMorphClass(),
            'actor_id' => $event->actor->record?->getKey(),
            'actor_kind' => $event->actor->kind,
            'payload' => $event->payload(),
        ]);
    }
}
