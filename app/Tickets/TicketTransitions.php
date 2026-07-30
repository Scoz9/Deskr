<?php

namespace App\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Tickets\Events\TicketAssigned;
use App\Tickets\Events\TicketCancelled;
use App\Tickets\Events\TicketClosed;
use App\Tickets\Events\TicketPutOnHold;
use App\Tickets\Events\TicketReopened;
use App\Tickets\Events\TicketResolved;
use App\Tickets\Events\TicketResumed;
use App\Tickets\Events\TicketReturnedToPool;
use App\Tickets\Events\TicketStarted;
use App\Tickets\Events\TicketTransitionEvent;
use Illuminate\Support\Facades\DB;

/**
 * The lifecycle table of §4, in one place: which status a ticket may move to,
 * what that passage is called, and nothing else. Keeping it here rather than on
 * the enum or scattered in `if`s is what makes an invalid passage impossible to
 * write by accident — the enum knows the seven statuses, this class knows the
 * arrows between them.
 *
 * It validates, writes the new status and emits the event of the passage. The
 * metric timestamps and `reopen_count` are not its business: they belong to the
 * Actions of steps 18-20, which are the ones that know why the passage is
 * happening.
 */
class TicketTransitions
{
    /**
     * The passages that leave the given status, each with the event it emits.
     *
     * The arrows and their events are one table and not two, so that an arrow
     * cannot end up allowed with nothing to announce it. The `match` is on the
     * enum and not on a keyed array so that adding a status without deciding
     * where it goes fails immediately, instead of silently returning "nowhere".
     * `chiuso` and `annullato` are terminal: they are here with an empty list
     * because a terminal status is a decision, not an omission.
     *
     * @return array<string, class-string<TicketTransitionEvent>>
     */
    private static function transitionsFrom(TicketStatus $from): array
    {
        return match ($from) {
            TicketStatus::Nuovo => [
                TicketStatus::Assegnato->value => TicketAssigned::class,
                TicketStatus::Annullato->value => TicketCancelled::class,
            ],
            TicketStatus::Assegnato => [
                TicketStatus::InLavorazione->value => TicketStarted::class,
                TicketStatus::InAttesa->value => TicketPutOnHold::class,
                TicketStatus::Nuovo->value => TicketReturnedToPool::class,
                TicketStatus::Annullato->value => TicketCancelled::class,
            ],
            TicketStatus::InLavorazione => [
                TicketStatus::InAttesa->value => TicketPutOnHold::class,
                TicketStatus::Risolto->value => TicketResolved::class,
                TicketStatus::Annullato->value => TicketCancelled::class,
            ],
            TicketStatus::InAttesa => [
                TicketStatus::InLavorazione->value => TicketResumed::class,
                TicketStatus::Risolto->value => TicketResolved::class,
            ],
            TicketStatus::Risolto => [
                TicketStatus::Chiuso->value => TicketClosed::class,
                TicketStatus::InLavorazione->value => TicketReopened::class,
            ],
            TicketStatus::Chiuso, TicketStatus::Annullato => [],
        };
    }

    /**
     * The statuses a ticket in the given status may move to, in the order the
     * brief lists them.
     *
     * @return list<TicketStatus>
     */
    public static function allowedFrom(TicketStatus $status): array
    {
        return array_map(
            static fn (string $value): TicketStatus => TicketStatus::from($value),
            array_keys(self::transitionsFrom($status)),
        );
    }

    /**
     * Whether the passage is one the lifecycle admits. A status is never a
     * transition to itself: standing still is not a passage, and letting it
     * through would emit an event and move the metrics for nothing.
     */
    public static function allows(TicketStatus $from, TicketStatus $to): bool
    {
        return isset(self::transitionsFrom($from)[$to->value]);
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

    /**
     * Make the passage: refuse it if the lifecycle does not admit it, write the
     * new status, then announce what happened.
     *
     * Both inside one transaction. The audit trail is written by a listener,
     * and a status that moves while the line explaining it is missing is worse
     * than neither — so either both land or the ticket never moved. The event
     * goes out after the save, so that whoever listens reads the ticket as it
     * now is, and any other pending change on the model rides along: the
     * Actions of step 20 set `resolved_at` before handing the ticket over, and
     * the timestamp and the status have to land together.
     *
     * @throws InvalidTicketTransition
     */
    public static function apply(Ticket $ticket, TicketStatus $to, TicketActor $actor): void
    {
        $from = $ticket->status;

        self::ensureAllowed($from, $to);

        $event = self::transitionsFrom($from)[$to->value];

        DB::transaction(function () use ($ticket, $from, $to, $actor, $event): void {
            $ticket->status = $to;
            $ticket->save();

            event(new $event($ticket, $from, $to, $actor));
        });
    }
}
