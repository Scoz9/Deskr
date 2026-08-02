<?php

namespace App\Listeners;

use App\Notifications\TicketResolved as TicketResolvedNotification;
use App\Tickets\Events\TicketResolved;

/**
 * Tells the requester their ticket is solved (roadmap step 37), reacting to
 * the same event the audit trail already listens on for a different reason
 * — {@see RecordTicketEvent} writes the fact, this tells the person waiting
 * on it, and neither has to know the other exists.
 *
 * Not queued, same as {@see RecordTicketEvent}: the work here is handing the
 * fact to `->notify()`, which is what queues the mail itself (§5) — queuing
 * the listener too would only add a second job for something that already
 * returns immediately.
 */
class SendTicketResolvedNotification
{
    public function handle(TicketResolved $event): void
    {
        $event->ticket->requester->notify(new TicketResolvedNotification($event->ticket));
    }
}
