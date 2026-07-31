<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Scrapkit\AuthorizesResources\Policies\BasePolicy;

/**
 * Who gets to a ticket. Two ways in, and they do not overlap.
 *
 * Agents and admins come in through the `ticket:*` permissions of the seeder,
 * and they see everything: the helpdesk is small and the operators have to be
 * able to cover for each other, so the team is a filter and not a boundary
 * (§3). The requester holds no permission at all (§5) and comes in by
 * ownership: their own ticket, and nothing else.
 */
class TicketPolicy extends BasePolicy
{
    /**
     * The requester reads what they asked for.
     *
     * This is the only filter standing between two customers, because there is
     * no global scoping to fall back on: it is on the person and not on the
     * organization, so a colleague of the same company is somebody else here
     * (§3).
     *
     * @param  User  $user
     * @param  Ticket  $model
     */
    public function view(Authorizable $user, Model $model): bool
    {
        return parent::view($user, $model) || $model->requester_id === $user->id;
    }
}
