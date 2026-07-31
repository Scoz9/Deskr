<?php

namespace App\Actions\Tickets;

use App\Enums\UserRole;
use App\Models\TicketMessage;
use Illuminate\Support\Facades\DB;

/**
 * The conversation: appends to the thread the reply the requester reads or the
 * note the team keeps to itself, and records when the team answered for the
 * first time.
 *
 * It is not a passage. Answering does not move a ticket through the lifecycle —
 * the transitions are the Action of step 20, and the reply that resumes a
 * ticket `in attesa` is the portal of step 27 — so nothing here goes through
 * `TicketTransitions` and nothing is written in the trail: the trail of §4
 * records transitions and assignments, and that a message was written is said
 * by the message itself.
 */
class ReplyToTicket
{
    /**
     * Write the message, and the metric if this is the one that starts it.
     *
     * Both in one transaction: a first response recorded on a ticket whose
     * message is missing is a metric measuring something that was never said.
     */
    public function __invoke(NewReply $reply): TicketMessage
    {
        return DB::transaction(function () use ($reply): TicketMessage {
            $message = $reply->ticket->messages()->create([
                'author_id' => $reply->author->getKey(),
                'body' => $reply->body,
                'is_internal' => $reply->isInternal,
            ]);

            if ($this->startsTheResponseTime($reply)) {
                // The timestamp of the message and not a second `now()`: the
                // metric measures the reply that is in the thread, to the
                // instant that reply carries.
                $reply->ticket->first_response_at = $message->created_at;
                $reply->ticket->save();
            }

            return $message;
        });
    }

    /**
     * Whether this message is the first time the team answered the requester.
     *
     * Three conditions, and each of them is the metric refusing to say
     * something that did not happen. An internal note is written for the team
     * and the requester never reads it. The requester adding to their own
     * request is not the team answering it. And first means first: the second
     * reply leaves the timestamp where the first one put it.
     */
    private function startsTheResponseTime(NewReply $reply): bool
    {
        if ($reply->isInternal || $reply->ticket->first_response_at !== null) {
            return false;
        }

        return $reply->author->hasRole([
            UserRole::Admin->value,
            UserRole::Agent->value,
        ]);
    }
}
