<?php

use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Database\Seeders\DemoSeeder;

/**
 * Seeding is expensive by design — the volume is the point, it is what makes
 * N+1 queries and pagination problems show up in phase 4 — so this file seeds
 * twice in total and groups everything it has to say about the demo data into
 * those two runs.
 */
test('the demo seeder builds the cast, the volume and the coverage the roadmap asks for', function () {
    $this->seed(DemoSeeder::class);

    expect(Organization::count())->toBe(3)
        ->and(Team::count())->toBe(2)
        ->and(User::role(UserRole::Agent->value)->count())->toBe(6)
        ->and(Ticket::count())->toBe(300);

    expect(Category::whereNull('team_id')->count())->toBe(0)
        ->and(User::role(UserRole::Requester->value)->whereNull('organization_id')->count())->toBe(0);

    foreach (TicketStatus::cases() as $status) {
        expect(Ticket::where('status', $status)->count())
            ->toBeGreaterThan(0, "no ticket in status {$status->value}");
    }

    foreach (TicketChannel::cases() as $channel) {
        expect(Ticket::where('channel', $channel)->count())
            ->toBeGreaterThan(0, "no ticket from channel {$channel->value}");
    }

    foreach (TicketPriority::cases() as $priority) {
        expect(Ticket::where('priority', $priority)->count())
            ->toBeGreaterThan(0, "no ticket with priority {$priority->value}");
    }

    /*
     * Pagination is one of the things this volume exists to stress, and three
     * hundred rows sharing one instant would hide exactly the bug it should
     * surface.
     */
    $days = Ticket::query()->pluck('created_at')->map->toDateString()->unique();

    expect($days->count())->toBeGreaterThan(30)
        ->and(Ticket::max('created_at'))->toBeLessThanOrEqual(now())
        ->and(Ticket::min('created_at'))->toBeGreaterThan(now()->subDays(120));

    /*
     * The demo is a starting point to run the application on, not something to
     * pile up: a second run finds the data already there and leaves it alone.
     */
    $this->seed(DemoSeeder::class);

    expect(Ticket::count())->toBe(300)
        ->and(Organization::count())->toBe(3)
        ->and(Team::count())->toBe(2);
});

test('the demo threads say as much as the status of their ticket says', function () {
    $this->seed(DemoSeeder::class);

    expect(Ticket::doesntHave('messages')->count())->toBe(0);

    /*
     * Every request starts with the requester describing the problem: the
     * ticket has no body of its own, so the description is the first message.
     */
    Ticket::with('messages')->take(25)->get()->each(function (Ticket $ticket) {
        $first = $ticket->messages->first();

        expect($first->author_id)->toBe($ticket->requester_id)
            ->and($first->is_internal)->toBeFalse()
            ->and($first->created_at->timestamp)->toBe($ticket->created_at->timestamp);
    });

    /*
     * A ticket that got past `assegnato` has been answered, and that reply is
     * the evidence the backfill of step 43 reads to write `first_response_at`.
     */
    $worked = Ticket::query()
        ->whereIn('status', [TicketStatus::InLavorazione, TicketStatus::InAttesa, TicketStatus::Risolto, TicketStatus::Chiuso])
        ->with('messages')
        ->take(25)
        ->get();

    expect($worked)->not->toBeEmpty();

    $worked->each(function (Ticket $ticket) {
        $reply = $ticket->messages
            ->where('is_internal', false)
            ->firstWhere('author_id', $ticket->assignee_id);

        expect($reply)->not->toBeNull("ticket {$ticket->reference} has no reply from its assignee")
            ->and($reply->created_at->timestamp)->toBeGreaterThan($ticket->created_at->timestamp);
    });

    $untouched = Ticket::query()
        ->whereIn('status', [TicketStatus::Nuovo, TicketStatus::Assegnato])
        ->withCount('messages')
        ->take(25)
        ->get();

    expect($untouched)->not->toBeEmpty()
        ->and($untouched->every(fn (Ticket $ticket): bool => $ticket->messages_count === 1))->toBeTrue();

    expect(TicketMessage::where('is_internal', true)->count())->toBeGreaterThan(0)
        ->and(TicketMessage::where('is_internal', false)->count())->toBeGreaterThan(0);

    /*
     * §4: the metrics are written by the Actions from step 19 on, and step 43
     * backfills them on exactly this data. Writing them here would leave that
     * step nothing to do and nothing to prove.
     */
    expect(Ticket::whereNotNull('first_response_at')->count())->toBe(0)
        ->and(Ticket::whereNotNull('resolved_at')->count())->toBe(0);

    expect(Ticket::where('status', TicketStatus::Chiuso)->whereNull('closed_at')->count())->toBe(0)
        ->and(Ticket::where('status', '!=', TicketStatus::Chiuso)->whereNotNull('closed_at')->count())->toBe(0)
        ->and(Ticket::where('reopen_count', '>', 0)->count())->toBeGreaterThan(0);

    /*
     * Only what a team has taken carries an assignee, and an assignee covers
     * the team the category routed the ticket to.
     */
    expect(Ticket::whereIn('status', [TicketStatus::Nuovo, TicketStatus::Annullato])->whereNotNull('assignee_id')->count())->toBe(0)
        ->and(Ticket::whereNotIn('status', [TicketStatus::Nuovo, TicketStatus::Annullato])->whereNull('assignee_id')->count())->toBe(0);

    Ticket::query()->whereNotNull('assignee_id')->with('assignee.teams')->take(25)->get()
        ->each(fn (Ticket $ticket) => expect($ticket->assignee->teams->pluck('id'))->toContain($ticket->team_id));

    /*
     * A ticket that came in by email carries the id of the message it came
     * from, which is what the threading of step 29 will match on.
     */
    $byEmail = Ticket::query()->where('channel', TicketChannel::Email)->with('messages')->take(10)->get();
    $byWeb = Ticket::query()->where('channel', TicketChannel::Web)->with('messages')->take(10)->get();

    expect($byEmail)->not->toBeEmpty()
        ->and($byEmail->every(fn (Ticket $t): bool => $t->messages->first()->external_message_id !== null))->toBeTrue()
        ->and($byWeb->every(fn (Ticket $t): bool => $t->messages->first()->external_message_id === null))->toBeTrue();
});
