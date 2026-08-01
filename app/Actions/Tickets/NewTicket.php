<?php

namespace App\Actions\Tickets;

use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Models\Category;
use App\Models\User;

/**
 * A request to open a ticket, in the shape every channel has to reduce itself
 * to: the web form, the inbound email and the agent taking a phone call all
 * build this and hand it to {@see CreateTicket}.
 *
 * This is what makes the channels interchangeable, and it is the case the kit
 * admits a DTO for — the boundary of a module. The alternative, loose
 * parameters, would let a channel added later quietly pass its own extra
 * argument and make the intake mean something different depending on who
 * called it.
 *
 * It carries models and not ids: the routing needs the team the category
 * points to, and the intake would otherwise read it back from the database
 * that the caller has already been through.
 */
class NewTicket
{
    public function __construct(
        public readonly User $requester,
        public readonly string $subject,
        public readonly string $body,
        public readonly TicketChannel $channel,
        /**
         * The taxonomy the request was filed under, and what the routing reads.
         * Null is a real case, not a missing value: an inbound email arrives
         * unclassified, and refusing it would mean losing the request.
         */
        public readonly ?Category $category = null,
        /**
         * `normale` unless the caller says otherwise. The public intake never
         * exposes it (§3) — if the requester chooses, everything is urgent —
         * while an agent opening a ticket on the phone does.
         */
        public readonly TicketPriority $priority = TicketPriority::Normale,
        /**
         * The files that came in with the description, already on disk. They
         * hang from the first message like every other attachment (§4): the
         * description is a message, so there is nothing special about the files
         * that arrive with it.
         *
         * @var list<NewAttachment>
         */
        public readonly array $attachments = [],
    ) {}
}
