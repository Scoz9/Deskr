<?php

namespace App\Enums;

/**
 * Who acted on a ticket. Not every action comes from a person (§4): the
 * automatic closing job acts as the system and the triage acts as the AI, and
 * neither has a record to point at. The kind is written next to the
 * polymorphic actor instead of being read out of it, so that an event stays
 * readable even when there is nothing on the other end of the relation.
 */
enum TicketActorType: string
{
    case Utente = 'utente';
    case Sistema = 'sistema';
    case Ai = 'ai';

    /**
     * Whether this kind of actor has a record behind it.
     */
    public function isPerson(): bool
    {
        return $this === self::Utente;
    }
}
