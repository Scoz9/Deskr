<?php

namespace App\Policies;

use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Scrapkit\AuthorizesResources\Policies\BasePolicy;

/**
 * Same ownership rule as {@see TicketPolicy}, with one more condition on top
 * of it: an internal note is written for the team and the requester never
 * reads it (§3), not even on their own ticket.
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

        return ! $model->is_internal && $user->id === $model->ticket->requester_id;
    }
}
