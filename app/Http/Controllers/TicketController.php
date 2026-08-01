<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The console: the operator's own view of every ticket, not just their own
 * (§3 — the team is a filter, not a boundary, so an `agent` triages the
 * whole backlog).
 */
class TicketController extends Controller
{
    /**
     * How many rows a page of the console shows. Small on purpose: the demo
     * seeder's 300 tickets exist to make pagination bugs show up here, not
     * to be hidden by a page large enough to fit them all on one screen.
     */
    public const PER_PAGE = 15;

    /**
     * The backlog, newest first, one page at a time.
     *
     * Paginated on the server and not sent whole like the users list: that
     * one is a handful of staff accounts, this is a ticket volume the demo
     * seeder chose specifically to make pagination bugs show up now instead
     * of the day the backlog grows past what a browser can hold at once.
     *
     * `id` breaks the tie between two tickets opened in the same second —
     * same reason the thread and the audit trail order on it too: without a
     * tiebreaker a page boundary can repeat or skip a row.
     */
    public function index(): Response
    {
        $tickets = Ticket::query()
            ->with(['requester.organization:id,name', 'team:id,name', 'assignee:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->through(fn (Ticket $ticket): array => [
                'id' => $ticket->id,
                'reference' => $ticket->reference,
                'subject' => $ticket->subject,
                'status' => $ticket->status->value,
                'priority' => $ticket->priority->value,
                'channel' => $ticket->channel->value,
                'requester' => $ticket->requester->name,
                'organization' => $ticket->requester->organization?->name,
                'team' => $ticket->team?->name,
                'assignee' => $ticket->assignee?->name,
                'openedAt' => $ticket->created_at?->toIso8601String(),
            ]);

        return Inertia::render('tickets/index', [
            'tickets' => [
                'data' => $tickets->items(),
                'meta' => [
                    'currentPage' => $tickets->currentPage(),
                    'perPage' => $tickets->perPage(),
                    'total' => $tickets->total(),
                ],
            ],
        ]);
    }
}
