<?php

namespace Database\Factories;

use App\Enums\TicketActorType;
use App\Enums\TicketEventType;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketEvent>
 */
class TicketEventFactory extends Factory
{
    /**
     * Define the model's default state: something an agent did, which is what
     * most of the trail is made of.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'type' => TicketEventType::Assegnato,
            'actor_type' => (new User)->getMorphClass(),
            'actor_id' => User::factory()->agent(),
            'actor_kind' => TicketActorType::Utente,
            'payload' => null,
        ];
    }

    /**
     * Written by a job and not by a person: the automatic closing of step 42
     * has nobody to attribute the transition to.
     */
    public function delSistema(): static
    {
        return $this->senzaAttore(TicketActorType::Sistema);
    }

    /**
     * Written by the triage of phase 6, which acts on its own and has to be
     * told apart from the system for the quality metric of step 53.
     */
    public function dellAi(): static
    {
        return $this->senzaAttore(TicketActorType::Ai);
    }

    /**
     * An actor with no record behind it: the kind is all that is left to say
     * who acted, which is why it is stored next to the relation.
     */
    private function senzaAttore(TicketActorType $kind): static
    {
        return $this->state(fn (): array => [
            'actor_type' => null,
            'actor_id' => null,
            'actor_kind' => $kind,
        ]);
    }
}
