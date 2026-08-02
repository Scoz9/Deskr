<?php

namespace App\Notifications\Concerns;

use App\Models\Ticket;
use Illuminate\Support\Facades\URL;

/**
 * The one door a requester opens a ticket through without an account: the
 * signed link every notification about a ticket hands out (§3). Extracted
 * once step 37 needed the exact same link the confirmation of step 25
 * already built, for the same reason a signature cannot be edited into
 * somebody else's request.
 */
trait LinksToTicket
{
    /**
     * How long the link stays good, in days (§5). Reusable until then.
     */
    public const LINK_DAYS = 7;

    public static function linkTo(Ticket $ticket): string
    {
        return URL::temporarySignedRoute(
            'support.ticket.show',
            now()->addDays(self::LINK_DAYS),
            ['ticket' => $ticket->getKey()],
        );
    }
}
