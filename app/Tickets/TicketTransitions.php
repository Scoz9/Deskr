<?php

namespace App\Tickets;

use App\Enums\TicketStatus;

/**
 * The lifecycle table of §4, in one place: which status a ticket may move to,
 * and nothing else. Keeping it here rather than on the enum or scattered in
 * `if`s is what makes an invalid passage impossible to write by accident — the
 * enum knows the seven statuses, this class knows the arrows between them.
 *
 * It validates and answers questions; it does not touch the ticket and does not
 * persist anything. Writing the new status, the metric timestamps and the audit
 * trail belongs to the Actions that cause the fact.
 */
class TicketTransitions
{
    /**
     * The statuses a ticket in the given status may move to, in the order the
     * brief lists them.
     *
     * The `match` is on the enum and not on a keyed table so that adding a
     * status without deciding where it goes fails immediately, instead of
     * silently returning "nowhere". `chiuso` and `annullato` are terminal: they
     * are here with an empty list because a terminal status is a decision, not
     * an omission.
     *
     * @return list<TicketStatus>
     */
    public static function allowedFrom(TicketStatus $status): array
    {
        return match ($status) {
            TicketStatus::Nuovo => [
                TicketStatus::Assegnato,
                TicketStatus::Annullato,
            ],
            TicketStatus::Assegnato => [
                TicketStatus::InLavorazione,
                TicketStatus::InAttesa,
                TicketStatus::Nuovo,
                TicketStatus::Annullato,
            ],
            TicketStatus::InLavorazione => [
                TicketStatus::InAttesa,
                TicketStatus::Risolto,
                TicketStatus::Annullato,
            ],
            TicketStatus::InAttesa => [
                TicketStatus::InLavorazione,
                TicketStatus::Risolto,
            ],
            TicketStatus::Risolto => [
                TicketStatus::Chiuso,
                TicketStatus::InLavorazione,
            ],
            TicketStatus::Chiuso, TicketStatus::Annullato => [],
        };
    }

    /**
     * Whether the passage is one the lifecycle admits. A status is never a
     * transition to itself: standing still is not a passage, and letting it
     * through would emit an event and move the metrics for nothing.
     */
    public static function allows(TicketStatus $from, TicketStatus $to): bool
    {
        return in_array($to, self::allowedFrom($from), true);
    }

    /**
     * Let an admitted passage through, and refuse every other one.
     *
     * @throws InvalidTicketTransition
     */
    public static function ensureAllowed(TicketStatus $from, TicketStatus $to): void
    {
        if (! self::allows($from, $to)) {
            throw InvalidTicketTransition::between($from, $to);
        }
    }
}
