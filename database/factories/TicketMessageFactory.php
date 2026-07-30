<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketMessage>
 */
class TicketMessageFactory extends Factory
{
    /**
     * Define the model's default state: the message a thread starts from — the
     * requester describing the problem, public, written in the application.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'author_id' => fn (array $attributes) => Ticket::query()
                ->whereKey($attributes['ticket_id'])
                ->value('requester_id'),
            'body' => fake()->paragraph(),
            'is_internal' => false,
            'external_message_id' => null,
        ];
    }

    /**
     * A reply the requester reads.
     */
    public function pubblica(): static
    {
        return $this->state(fn (): array => [
            'is_internal' => false,
        ]);
    }

    /**
     * A note that stays in the team. Written by an agent by definition: the
     * requester has no way to write one.
     */
    public function interna(): static
    {
        return $this->dellOperatore()->state(fn (): array => [
            'is_internal' => true,
        ]);
    }

    /**
     * Written from the console instead of from the portal, so the author is an
     * agent and not the requester of the ticket.
     */
    public function dellOperatore(): static
    {
        return $this->state(fn (): array => [
            'author_id' => User::factory()->agent(),
        ]);
    }

    /**
     * Came in by email, carrying the id of the message it was threaded from.
     * Public: an inbound email is never an internal note.
     */
    public function daEmail(): static
    {
        return $this->pubblica()->state(fn (): array => [
            'external_message_id' => sprintf('<%s@%s>', fake()->uuid(), fake()->domainName()),
        ]);
    }
}
