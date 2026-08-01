<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Scrapkit\AuthorizesResources\Policies\BasePolicy;

/**
 * An `agent` sees every ticket — the team is a filter, not a boundary (§3),
 * so nothing here narrows what the console permission already grants. A
 * `requester` is seeded with no console permission at all (§5): the portal
 * is guarded by ownership, not by an ability.
 */
class TicketPolicy extends BasePolicy
{
    /**
     * @param  User  $user
     * @param  Ticket  $model
     */
    public function view(Authorizable $user, Model $model): bool
    {
        return parent::view($user, $model) || $user->id === $model->requester_id;
    }
}
