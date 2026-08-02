<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\NewReply;
use App\Actions\Tickets\ReplyToTicket;
use App\Concerns\StoresAttachmentUploads;
use App\Http\Requests\Tickets\TicketReplyRequest;
use App\Models\Ticket;
use App\Notifications\TicketReplied;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The console side of a ticket's thread: appending the reply the requester
 * reads or the note the team keeps to itself, both through the same
 * `ReplyToTicket` the portal and the inbound email already write to (§4 —
 * one fact with a flag on it, not two).
 *
 * The model this authorizes against is `TicketMessage`, deduced from the
 * controller name, and not `Ticket`: `ticketMessage:create` is a class-level
 * ability — a message that does not exist yet has no instance to check
 * `ticket:update` against, and writing a note is not "updating the ticket"
 * in the sense that ability means for step 35's actions.
 */
class TicketMessageController extends Controller
{
    use StoresAttachmentUploads;

    /**
     * Append the message, and the attachments it carries, to the thread.
     */
    public function store(TicketReplyRequest $request, Ticket $ticket): RedirectResponse
    {
        $isInternal = $request->boolean('is_internal');

        app(ReplyToTicket::class)(new NewReply(
            ticket: $ticket,
            author: $request->user(),
            body: $request->string('body')->toString(),
            isInternal: $isInternal,
            attachments: $this->storeAttachmentUploads($request->file('attachments', [])),
        ));

        // Only the reply the requester can actually read is worth telling
        // them about (roadmap step 37) — a note is written for the team and
        // never reaches the portal thread either (§3).
        if (! $isInternal) {
            $ticket->requester->notify(new TicketReplied($ticket));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Message added.')]);

        return back();
    }
}
