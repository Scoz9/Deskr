<?php

namespace App\Actions\Tickets;

use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Tickets\TicketActor;
use Illuminate\Support\Facades\DB;

/**
 * What a requester's reply from the portal does to the ticket it lands on —
 * one of three things, chosen by the status it finds (roadmap step 27, and
 * the "Risposta su ticket chiuso" decision of §3).
 *
 * Waiting on the requester or already solved, the reply is the fact that
 * earns the ticket the arrow back to `in lavorazione` — the same passage
 * {@see TransitionTicket} already admits, so this only decides when to ask
 * for it. Closed is terminal and admits no passage at all: a closed ticket
 * does not reopen, the reply fathers a new one carrying `parent_ticket_id`
 * instead of pretending months later that the old one moved. Every other
 * status is a ticket already alive with nobody waiting on the requester, so
 * the reply is simply appended to the thread.
 */
class ReplyFromPortal
{
    public function __invoke(PortalReply $request): Ticket
    {
        return match ($request->ticket->status) {
            TicketStatus::Chiuso => $this->openFollowUp($request),
            TicketStatus::InAttesa, TicketStatus::Risolto => $this->resume($request),
            default => $this->reply($request),
        };
    }

    /**
     * A closed ticket does not reopen (§3): what the reply opens instead is a
     * ticket of its own, linked to the one it followed up on.
     */
    private function openFollowUp(PortalReply $request): Ticket
    {
        $parent = $request->ticket;

        return app(CreateTicket::class)(new NewTicket(
            requester: $request->requester,
            subject: $parent->subject,
            body: $request->body,
            channel: TicketChannel::Web,
            category: $parent->category,
            parentTicket: $parent,
        ));
    }

    /**
     * The reply and the passage it earns land together: a ticket resumed with
     * no message behind it is a status that moved for a reason nobody wrote
     * down.
     */
    private function resume(PortalReply $request): Ticket
    {
        return DB::transaction(function () use ($request): Ticket {
            $this->reply($request);

            return app(TransitionTicket::class)(new TicketTransitionRequest(
                ticket: $request->ticket,
                status: TicketStatus::InLavorazione,
                actor: TicketActor::user($request->requester),
            ));
        });
    }

    private function reply(PortalReply $request): Ticket
    {
        app(ReplyToTicket::class)(new NewReply(
            ticket: $request->ticket,
            author: $request->requester,
            body: $request->body,
        ));

        return $request->ticket;
    }
}
