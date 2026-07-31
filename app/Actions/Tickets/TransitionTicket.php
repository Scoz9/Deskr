<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Tickets\InvalidTicketTransition;
use App\Tickets\TicketTransitions;
use Illuminate\Support\Carbon;

/**
 * The five things an agent does to a ticket that is already in their hands:
 * resolve it, close it, reopen it, put it on hold, cancel it.
 *
 * One use case and not five, because what tells the passages apart is the table
 * of §4 and the Action has nothing to add to it: it asks {@see
 * TicketTransitions} whether the arrow exists, writes the metrics that arrow
 * moves and lets the transition do the rest. A method per verb would be the
 * same table written twice, and the day an arrow is added the second copy is
 * the one nobody updates.
 *
 * That it takes any admitted passage and not only the five is what lets the
 * portal of step 27 and the automatic closings of step 42 come through here
 * too, instead of writing the timestamps a second time somewhere else.
 */
class TransitionTicket
{
    /**
     * Move the ticket, with the metrics the passage is worth.
     *
     * The timestamps are set on the model before the transition and not after:
     * it is the transition that saves, so status and metric land in the same
     * write and there is never an instant where the ticket is resolved without
     * saying when.
     *
     * @throws InvalidTicketTransition
     */
    public function __invoke(TicketTransitionRequest $request): Ticket
    {
        $ticket = $request->ticket;
        $from = $ticket->status;

        // Asked before anything is touched, so that a refused passage leaves
        // the model as clean as the row: a `resolved_at` pending on a ticket
        // that never moved would be written by whoever saves it next.
        TicketTransitions::ensureAllowed($from, $request->status);

        $this->measure($ticket, $from, $request->status);

        TicketTransitions::apply($ticket, $request->status, $request->actor);

        return $ticket;
    }

    /**
     * The metric timestamps the passage moves, read from the pair it goes
     * between exactly like the events are.
     *
     * Both arrows that land on `risolto` write the resolution: what is measured
     * is the ticket being solved, not the status it was solved from. The
     * reopening, instead, is only the one that leaves `risolto` — the requester
     * answering a ticket on hold resumes something that was never solved, and
     * counting it would inflate the reopening rate of step 46 with tickets that
     * never came back.
     */
    private function measure(Ticket $ticket, TicketStatus $from, TicketStatus $to): void
    {
        if ($to === TicketStatus::Risolto) {
            $ticket->resolved_at = Carbon::now();
        }

        if ($to === TicketStatus::Chiuso) {
            $ticket->closed_at = Carbon::now();
        }

        if ($from === TicketStatus::Risolto && $to === TicketStatus::InLavorazione) {
            $ticket->reopen_count++;

            // A reopened ticket is not solved. Leaving the timestamp there
            // would tell the dashboard a resolution that is no longer true,
            // and the resolution that comes after writes it again anyway.
            $ticket->resolved_at = null;
        }
    }
}
