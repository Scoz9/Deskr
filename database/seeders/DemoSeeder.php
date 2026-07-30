<?php

namespace Database\Seeders;

use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The demo dataset: a helpdesk that has been running for three months, with
 * enough volume to make N+1 queries and pagination problems show up in phase 4
 * and to give the similarity search of phase 6 something to retrieve.
 *
 * Deliberately not part of `DatabaseSeeder`: that one runs on any fresh
 * install, and three hundred invented tickets are not something an empty
 * application should wake up with. Run it with
 * `artisan db:seed --class=DemoSeeder`.
 *
 * Model events stay on, unlike `DatabaseSeeder`: the public reference of a
 * ticket is drawn on `creating`, so switching them off would insert three
 * hundred tickets with no reference.
 */
class DemoSeeder extends Seeder
{
    private const ORGANIZATIONS = 3;

    private const TEAMS = 2;

    private const AGENTS = 6;

    private const REQUESTERS_PER_ORGANIZATION = 5;

    private const CATEGORIES_PER_TEAM = 3;

    private const TICKETS = 300;

    /**
     * How far back the oldest ticket was opened.
     */
    private const DAYS = 90;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Ticket::query()->exists()) {
            $this->command->warn('The demo data is already there — nothing to seed.');

            return;
        }

        $this->call([PermissionSeeder::class, RoleSeeder::class]);

        /*
         * All of it or none of it. The guard above looks at the tickets, which
         * are written last: a run that dies halfway would otherwise leave the
         * cast behind and be doubled by the next attempt.
         */
        DB::transaction(function (): void {
            $teams = Team::factory()->count(self::TEAMS)->create();
            $agents = $this->agentsCovering($teams);
            $categories = $teams->flatMap(fn (Team $team): Collection => Category::factory()
                ->count(self::CATEGORIES_PER_TEAM)
                ->for($team)
                ->create());

            $this->tickets($categories, $agents, $this->requesters());
        });

        $this->command->info(sprintf(
            '%d tickets over %d days, %d organizations, %d teams, %d agents.',
            self::TICKETS,
            self::DAYS,
            self::ORGANIZATIONS,
            self::TEAMS,
            self::AGENTS,
        ));
    }

    /**
     * The agents, spread over the teams so that every team has somebody to
     * route to.
     *
     * @param  Collection<int, Team>  $teams
     * @return Collection<int, User>
     */
    private function agentsCovering(Collection $teams): Collection
    {
        return User::factory()
            ->count(self::AGENTS)
            ->agent()
            ->create()
            ->each(fn (User $agent, int $index) => $agent->teams()->attach($teams[$index % $teams->count()]));
    }

    /**
     * The people writing in, each one working for one of the organizations.
     *
     * @return Collection<int, User>
     */
    private function requesters(): Collection
    {
        return Organization::factory()
            ->count(self::ORGANIZATIONS)
            ->create()
            ->flatMap(fn (Organization $organization): Collection => User::factory()
                ->count(self::REQUESTERS_PER_ORGANIZATION)
                ->requester()
                ->for($organization)
                ->create());
    }

    /**
     * How the tickets are spread over the lifecycle: a backlog small enough to
     * look like a team that keeps up, and most of the volume behind it.
     *
     * @return array<string, int>
     */
    private function statusMix(): array
    {
        return [
            TicketStatus::Nuovo->value => 45,
            TicketStatus::Assegnato->value => 30,
            TicketStatus::InLavorazione->value => 60,
            TicketStatus::InAttesa->value => 35,
            TicketStatus::Risolto->value => 55,
            TicketStatus::Chiuso->value => 60,
            TicketStatus::Annullato->value => 15,
        ];
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @param  Collection<int, User>  $agents
     * @param  Collection<int, User>  $requesters
     */
    private function tickets(Collection $categories, Collection $agents, Collection $requesters): void
    {
        $channels = TicketChannel::cases();
        $priorities = TicketPriority::cases();

        /** @var array<int, Collection<int, User>> $agentsByTeam */
        $agentsByTeam = $agents->groupBy(fn (User $agent): int => $agent->teams->first()->id)->all();

        $index = 0;

        foreach ($this->statusMix() as $status => $count) {
            $status = TicketStatus::from($status);

            for ($written = 0; $written < $count; $written++, $index++) {
                $openedAt = $this->openedAt($index);
                $requester = $requesters->random();
                $category = $categories->random();
                $assignee = $this->needsAnAssignee($status)
                    ? $agentsByTeam[$category->team_id]->random()
                    : null;

                $ticket = Ticket::factory()->create([
                    'status' => $status,
                    'priority' => $priorities[$index % count($priorities)],
                    'channel' => $channels[$index % count($channels)],
                    'requester_id' => $requester->id,
                    'organization_id' => $requester->organization_id,
                    'category_id' => $category->id,
                    'team_id' => $category->team_id,
                    'assignee_id' => $assignee?->id,
                    'reopen_count' => $status === TicketStatus::InLavorazione && $written % 5 === 0 ? 1 : 0,
                    'closed_at' => $status === TicketStatus::Chiuso ? $openedAt->copy()->addDays(3) : null,
                    'created_at' => $openedAt,
                    'updated_at' => $openedAt,
                ]);

                $this->thread($ticket, $assignee, $index);
            }
        }
    }

    /**
     * Whether a ticket in this status is on somebody's desk. A cancelled ticket
     * never is: the whole point of the status is getting rid of spam without
     * handing it to a real agent first.
     */
    private function needsAnAssignee(TicketStatus $status): bool
    {
        return ! in_array($status, [TicketStatus::Nuovo, TicketStatus::Annullato], true);
    }

    /**
     * The thread of a ticket, as far as its status says it got: the request
     * always, the reply only where somebody has answered.
     *
     * The reply is what the backfill of step 43 will read to write
     * `first_response_at`, which is why this seeder leaves that column — and
     * `resolved_at` — alone: writing the metrics here would leave that step
     * nothing to work on and nothing to prove.
     */
    private function thread(Ticket $ticket, ?User $assignee, int $index): void
    {
        $describedAt = $ticket->created_at;

        TicketMessage::factory()
            ->when(
                $ticket->channel === TicketChannel::Email,
                fn ($factory) => $factory->daEmail(),
            )
            ->create([
                'ticket_id' => $ticket->id,
                'author_id' => $ticket->requester_id,
                'is_internal' => false,
                'created_at' => $describedAt,
                'updated_at' => $describedAt,
            ]);

        if ($assignee === null || $ticket->status === TicketStatus::Assegnato) {
            return;
        }

        if ($index % 4 === 0) {
            $notedAt = $describedAt->copy()->addHours(2);

            TicketMessage::factory()->create([
                'ticket_id' => $ticket->id,
                'author_id' => $assignee->id,
                'is_internal' => true,
                'created_at' => $notedAt,
                'updated_at' => $notedAt,
            ]);
        }

        $repliedAt = $describedAt->copy()->addHours(4);

        TicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'author_id' => $assignee->id,
            'is_internal' => false,
            'created_at' => $repliedAt,
            'updated_at' => $repliedAt,
        ]);
    }

    /**
     * When a ticket was opened. The tickets are written oldest lifecycle last,
     * so walking the mix in order spreads them from today back to three months
     * ago and leaves the backlog recent — as it would be in a helpdesk that
     * keeps up.
     */
    private function openedAt(int $index): Carbon
    {
        return Carbon::now()
            ->subDays(intdiv($index * self::DAYS, self::TICKETS))
            ->subMinutes(fake()->numberBetween(0, 60 * 23));
    }
}
