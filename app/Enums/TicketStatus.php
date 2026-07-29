<?php

namespace App\Enums;

/**
 * Lifecycle of a ticket, persisted on a string column.
 *
 * There is no reopened case: reopening is the `risolto` → `in_lavorazione`
 * transition. The allowed transitions live in the transition class, not here.
 */
enum TicketStatus: string
{
    case Nuovo = 'nuovo';
    case Assegnato = 'assegnato';
    case InLavorazione = 'in_lavorazione';
    case InAttesa = 'in_attesa';
    case Risolto = 'risolto';
    case Chiuso = 'chiuso';
    case Annullato = 'annullato';
}
