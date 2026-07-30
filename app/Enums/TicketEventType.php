<?php

namespace App\Enums;

/**
 * The vocabulary of the audit trail: what is written on `ticket_events.type`.
 *
 * Left open in phase 1 on purpose — a vocabulary decided before the events
 * exist is a guess. There are nine names for thirteen passages because what a
 * transition means is the pair it goes between and not the status it arrives
 * at: three arrows land on `annullato`, and two each on `risolto` and
 * `in_attesa`, without meaning anything different once there.
 *
 * Not every name here is a passage: `riassegnato` is a ticket changing hands
 * without moving, which the trail has to remember all the same.
 *
 * The column stays a string: an enum in the schema would need an `ALTER TYPE`
 * to grow, and this vocabulary grows at every step that adds a fact worth
 * recording.
 */
enum TicketEventType: string
{
    case Assegnato = 'ticket.assegnato';
    case Riassegnato = 'ticket.riassegnato';
    case PresoInCarico = 'ticket.preso_in_carico';
    case RimessoNelPool = 'ticket.rimesso_nel_pool';
    case MessoInAttesa = 'ticket.messo_in_attesa';
    case Ripreso = 'ticket.ripreso';
    case Risolto = 'ticket.risolto';
    case Chiuso = 'ticket.chiuso';
    case Annullato = 'ticket.annullato';
    case Riaperto = 'ticket.riaperto';
}
