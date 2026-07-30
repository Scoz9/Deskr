<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Tickets\Events\TicketReassigned;
use App\Tickets\TicketTransitions;
use Illuminate\Support\Facades\DB;

/**
 * Puts a ticket in somebody's hands.
 *
 * Two different facts behind one use case. Out of the pool it is the transition
 * of §4, `nuovo` → `assegnato`, and it goes through {@see TicketTransitions} so
 * that the arrow and its event stay where every other arrow lives. From any
 * other status it is a handover and nothing else: the assignee is an attribute
 * and not a state (§3), and walking a ticket in lavorazione back to `assegnato`
 * would falsify the metrics of a ticket that has not moved.
 */
class AssignTicket
{
    /**
     * Hand the ticket over, and record which of the two things happened.
     */
    public function __invoke(TicketAssignment $request): Ticket
    {
        $ticket = $request->ticket;
        $previous = $ticket->assignee;

        // Giving a ticket to whoever already has it is the assignment
        // equivalent of a status transitioning to itself: nothing moved, so
        // nothing is announced and the trail stays quiet.
        if ($previous !== null && $previous->is($request->assignee)) {
            return $ticket;
        }

        return DB::transaction(function () use ($ticket, $request, $previous): Ticket {
            $ticket->assignee_id = $request->assignee->getKey();

            if ($ticket->status === TicketStatus::Nuovo) {
                // The transition saves the ticket, and the pending assignee
                // rides along: the ticket lands assigned to somebody in one
                // write, never assigned to nobody for an instant.
                TicketTransitions::apply($ticket, TicketStatus::Assegnato, $request->actor);

                return $ticket;
            }

            $ticket->save();

            event(new TicketReassigned($ticket, $previous, $request->assignee, $request->actor));

            return $ticket;
        });
    }
}
