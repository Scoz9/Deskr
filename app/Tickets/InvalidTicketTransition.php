<?php

namespace App\Tickets;

use App\Enums\TicketStatus;
use DomainException;

/**
 * A passage the lifecycle of §4 does not admit. It is a `DomainException` and
 * not a validation error because it means the caller asked for something the
 * domain has no answer to: the interface must never offer a transition that
 * ends up here.
 */
class InvalidTicketTransition extends DomainException
{
    /**
     * Name both ends of the refused passage: an exception that only says
     * "invalid transition" sends whoever reads the log back to the code to find
     * out which one.
     */
    public static function between(TicketStatus $from, TicketStatus $to): self
    {
        return new self(sprintf(
            'A ticket cannot go from %s to %s.',
            $from->value,
            $to->value,
        ));
    }
}
