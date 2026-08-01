<?php

namespace App\Http\Controllers;

use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
     * The backlog, newest first, one page at a time, narrowed by whichever
     * filters are on the query string.
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
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'channel' => ['nullable', Rule::enum(TicketChannel::class)],
            'team_id' => ['nullable', 'integer', Rule::exists(Team::class, 'id')],
            'assignee' => ['nullable', $this->assigneeRule()],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $tickets = Ticket::query()
            ->with(['requester.organization:id,name', 'team:id,name', 'assignee:id,name'])
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(filled($filters['priority'] ?? null), fn (Builder $query) => $query->where('priority', $filters['priority']))
            ->when(filled($filters['channel'] ?? null), fn (Builder $query) => $query->where('channel', $filters['channel']))
            ->when(filled($filters['team_id'] ?? null), fn (Builder $query) => $query->where('team_id', $filters['team_id']))
            ->when(filled($filters['assignee'] ?? null), fn (Builder $query) => $filters['assignee'] === 'unassigned'
                ? $query->whereNull('assignee_id')
                : $query->where('assignee_id', $filters['assignee']))
            ->when(filled($filters['search'] ?? null), fn (Builder $query) => $this->applySearch($query, $filters['search']))
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
            'filters' => [
                'status' => $filters['status'] ?? null,
                'priority' => $filters['priority'] ?? null,
                'channel' => $filters['channel'] ?? null,
                'teamId' => isset($filters['team_id']) ? (int) $filters['team_id'] : null,
                'assignee' => $filters['assignee'] ?? null,
                'search' => $filters['search'] ?? null,
            ],
            'filterOptions' => [
                'teams' => Team::query()->orderBy('name')->get(['id', 'name']),
                'assignees' => $this->assignableUsers()->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    /**
     * The full-text search of step 33, on Postgres' own dictionary and not
     * a search engine of its own (§3 — the reason the project runs on
     * Postgres rather than MySQL in the first place). One `where` group so
     * the four `or`s stay together and never leak into the filters around
     * them: subject, the requester's name, their organisation's name, and
     * every message in the thread — a match on any one is a match on the
     * ticket.
     *
     * @param  Builder<Ticket>  $query
     */
    private function applySearch(Builder $query, string $term): void
    {
        $query->where(function (Builder $query) use ($term): void {
            $query->whereFullText('subject', $term, ['language' => 'italian'])
                ->orWhereHas(
                    'requester',
                    fn (Builder $query) => $query->whereFullText('name', $term, ['language' => 'italian']),
                )
                ->orWhereHas(
                    'requester.organization',
                    fn (Builder $query) => $query->whereFullText('name', $term, ['language' => 'italian']),
                )
                ->orWhereHas(
                    'messages',
                    fn (Builder $query) => $query->whereFullText('body', $term, ['language' => 'italian']),
                );
        });
    }

    /**
     * Every user a ticket may be assigned to: an `agent` or an `admin`.
     *
     * A `whereHas` on the relation and not spatie's `role()` scope, which
     * looks the role names up and throws if either one is not seeded yet —
     * fatal for a query that only wants to know who currently holds them.
     *
     * @return Builder<User>
     */
    private function assignableUsers(): Builder
    {
        return User::query()->whereHas(
            'roles',
            fn (Builder $query) => $query->whereIn('name', [UserRole::Admin->value, UserRole::Agent->value]),
        );
    }

    /**
     * The assignee filter takes a user id or the literal "unassigned" — the
     * pool of tickets nobody has picked up, which is not a person to look
     * up by id.
     */
    private function assigneeRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === 'unassigned') {
                return;
            }

            if (! is_numeric($value) || ! $this->assignableUsers()->whereKey($value)->exists()) {
                $fail('Assegnatario non valido.');
            }
        };
    }
}
