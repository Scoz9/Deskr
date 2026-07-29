<?php

namespace App\Enums;

/**
 * Channel a ticket entered from. Each channel is an adapter that builds the
 * `CreateTicket` DTO, so a new channel is a new case plus its adapter.
 */
enum TicketChannel: string
{
    case Web = 'web';
    case Email = 'email';
    case Telefono = 'telefono';
}
