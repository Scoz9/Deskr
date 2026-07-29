<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Scrapkit\AuthorizesResources\Policies\BasePolicy;

class UserPolicy extends BasePolicy
{
    /**
     * @param  User  $user
     * @param  User  $model
     */
    public function update(Authorizable $user, Model $model): bool
    {
        return parent::update($user, $model) && $user->canManage($model);
    }

    public function suspend(User $user, User $target): bool
    {
        return ! $user->is($target) && $user->can($this->permission('suspend')) && $user->canManage($target);
    }

    public function unsuspend(User $user, User $target): bool
    {
        return $user->can($this->permission('suspend')) && $user->canManage($target);
    }
}
