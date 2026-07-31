<?php

namespace App\Policies;

use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Scrapkit\AuthorizesResources\Policies\BasePolicy;

/**
 * Who gets to a message of the thread. The ownership is the ticket's, because
 * a message has no requester of its own — it is read by whoever may read the
 * ticket it hangs from.
 *
 * With one thing on top that the ticket does not have: the internal note. It is
 * written for the team and the requester never sees it, on their own ticket
 * either — a thread that lets it through has published it.
 */
class TicketMessagePolicy extends BasePolicy
{
    /**
     * @param  User  $user
     * @param  TicketMessage  $model
     */
    public function view(Authorizable $user, Model $model): bool
    {
        if (parent::view($user, $model)) {
            return true;
        }

        return ! $model->is_internal && $model->ticket->requester_id === $user->id;
    }
}
