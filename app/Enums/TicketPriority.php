<?php

namespace App\Enums;

/**
 * Priority of a ticket. The public intake never exposes it: every ticket
 * starts at `normale` and only an agent changes it.
 */
enum TicketPriority: string
{
    case Bassa = 'bassa';
    case Normale = 'normale';
    case Alta = 'alta';
    case Urgente = 'urgente';
}
