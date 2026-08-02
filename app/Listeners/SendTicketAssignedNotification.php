<?php

namespace App\Listeners;

use App\Notifications\TicketAssigned as TicketAssignedNotification;
use App\Tickets\Events\TicketAssigned;

/**
 * Tells the agent a ticket just left the pool for their queue (roadmap step
 * 38) — the same event {@see RecordTicketEvent} already listens on for the
 * audit trail, and its own docblock is where this step was meant to hang.
 *
 * Silent on a self-assignment: "assegna a me" of step 35 is the only door
 * that reaches this event today, and it is always the agent finding out by
 * their own click — telling them again would be an email about the button
 * they just pressed. The check reads the actor and not the caller, so it
 * keeps holding the day a ticket is handed out by somebody else instead.
 *
 * Not queued, same as {@see RecordTicketEvent}: the work here is handing
 * the fact to `->notify()`, which is what queues the mail itself (§5).
 */
class SendTicketAssignedNotification
{
    public function handle(TicketAssigned $event): void
    {
        // `assignee()`, not the `assignee` property: `AssignTicket` reads
        // the relation before setting `assignee_id`, and a cached "nobody"
        // does not un-cache itself just because the column changed under it.
        $assignee = $event->ticket->assignee()->first();

        if ($assignee === null || $event->actor->record?->is($assignee)) {
            return;
        }

        $assignee->notify(new TicketAssignedNotification($event->ticket));
    }
}
