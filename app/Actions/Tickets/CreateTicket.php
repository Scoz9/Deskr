<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * The intake: turns a request into a ticket, wherever it came from.
 *
 * It is the only door into the system, and it takes a {@see NewTicket} DTO and
 * not loose parameters — that is what makes a channel an adapter that builds
 * the DTO instead of a second, slightly different way of opening a ticket.
 *
 * Two things happen here and nowhere else: the ticket is routed to the team the
 * category carries, and the description is written as the first message of the
 * thread. The routing is deterministic on purpose (§3): the triage of phase 6
 * is a layer above a system that already works when the provider is down.
 */
class CreateTicket
{
    /**
     * Open the ticket and start its thread.
     *
     * Both in one transaction: a ticket whose first message is missing has lost
     * the request it was opened for, and an intake that half-succeeded is worse
     * than one that failed — the requester would have to be told to write again
     * about something that is already in the list.
     */
    public function __invoke(NewTicket $request): Ticket
    {
        return DB::transaction(function () use ($request): Ticket {
            $ticket = Ticket::create([
                'subject' => $request->subject,
                'status' => TicketStatus::Nuovo,
                'priority' => $request->priority,
                'channel' => $request->channel,
                'requester_id' => $request->requester->getKey(),
                // Copied over from the requester, so that the reporting per
                // customer holds even if they later move company.
                'organization_id' => $request->requester->organization_id,
                'category_id' => $request->category?->getKey(),
                // Read once, at the intake, and written on the row: re-routing
                // a category later must not rewrite where the tickets already
                // handled went.
                'team_id' => $request->category?->team_id,
                'parent_ticket_id' => $request->parentTicket?->getKey(),
                'reopen_count' => 0,
            ]);

            $message = $ticket->messages()->create([
                'author_id' => $request->requester->getKey(),
                'body' => $request->body,
                'is_internal' => false,
            ]);

            // The rows land in the same transaction as the message they hang
            // from: a file on disk nobody points at is rubbish to collect, a row
            // pointing at nothing is a broken link in the thread.
            foreach ($request->attachments as $attachment) {
                $message->attachments()->create([
                    'disk' => $attachment->disk,
                    'path' => $attachment->path,
                    'original_name' => $attachment->originalName,
                    'mime_type' => $attachment->mimeType,
                    'size' => $attachment->size,
                ]);
            }

            return $ticket;
        });
    }
}
