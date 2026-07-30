<?php

namespace App\Tickets;

use App\Enums\TicketActorType;
use App\Models\User;

/**
 * Who is acting on a ticket, in the only two shapes the domain admits: a person
 * with a record behind them, or a kind with nothing behind it (§4).
 *
 * It exists so that `actor_kind` and the polimorphic actor cannot be made to
 * disagree: they are set together here, once, instead of being passed around as
 * two arguments that every caller has to keep consistent. The constructor is
 * private for the same reason — there is no third shape to build.
 */
class TicketActor
{
    private function __construct(
        public readonly TicketActorType $kind,
        public readonly ?User $record,
    ) {}

    /**
     * A person: an agent from the console, a requester from the portal.
     */
    public static function user(User $user): self
    {
        return new self(TicketActorType::Utente, $user);
    }

    /**
     * The application acting on its own — the automatic closing job of step 42.
     */
    public static function system(): self
    {
        return new self(TicketActorType::Sistema, null);
    }

    /**
     * The triage of phase 6, told apart from the system because the quality
     * metric of step 53 has to know which suggestions were acted on.
     */
    public static function ai(): self
    {
        return new self(TicketActorType::Ai, null);
    }
}
