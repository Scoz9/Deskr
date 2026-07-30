<?php

namespace Database\Factories;

use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state: a ticket as the intake leaves it —
     * `nuovo`, normal priority, nobody assigned, no metric measured yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject' => rtrim(fake()->sentence(4), '.'),
            'status' => TicketStatus::Nuovo,
            'priority' => TicketPriority::Normale,
            'channel' => fake()->randomElement(TicketChannel::cases()),
            'requester_id' => User::factory()->requester()->for(Organization::factory()),
            'organization_id' => fn (array $attributes) => User::query()
                ->whereKey($attributes['requester_id'])
                ->value('organization_id'),
            'category_id' => Category::factory(),
            'team_id' => fn (array $attributes) => Category::query()
                ->whereKey($attributes['category_id'])
                ->value('team_id'),
            'reopen_count' => 0,
            'created_at' => $this->openedAt(),
            'updated_at' => $this->openedAt(),
        ];
    }

    /**
     * A ticket nobody has taken yet, straight out of the intake.
     */
    public function nuovo(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::Nuovo,
        ]);
    }

    /**
     * Taken out of the pool by an agent, not worked on yet.
     */
    public function assegnato(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::Assegnato,
            'assignee_id' => User::factory()->agent(),
        ]);
    }

    /**
     * Being worked on. Still no first response: taking a ticket is not
     * answering it, and the metric must not say otherwise.
     */
    public function inLavorazione(): static
    {
        return $this->assegnato()->state(fn (): array => [
            'status' => TicketStatus::InLavorazione,
        ]);
    }

    /**
     * Waiting on the requester, which only happens after the agent has replied:
     * the first response is therefore always recorded.
     */
    public function inAttesa(): static
    {
        return $this->inLavorazione()->state(fn (): array => [
            'status' => TicketStatus::InAttesa,
            'first_response_at' => $this->openedAt()->addHours(4),
        ]);
    }

    /**
     * Solved, and still open to a reply that reopens it.
     */
    public function risolto(): static
    {
        return $this->inAttesa()->state(fn (): array => [
            'status' => TicketStatus::Risolto,
            'resolved_at' => $this->openedAt()->addDay(),
        ]);
    }

    /**
     * Closed for good: a reply on this one opens a follow-up ticket.
     */
    public function chiuso(): static
    {
        return $this->risolto()->state(fn (): array => [
            'status' => TicketStatus::Chiuso,
            'closed_at' => $this->openedAt()->addDays(2),
        ]);
    }

    /**
     * Cancelled — spam or an invalid request. Terminal, and never assigned to a
     * real agent just to get rid of it.
     */
    public function annullato(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::Annullato,
        ]);
    }

    /**
     * When the ticket was opened. Every state hangs its metric timestamps on
     * this anchor, so that a first response never predates the request.
     */
    private function openedAt(): Carbon
    {
        return Carbon::now()->subDays(2);
    }
}
