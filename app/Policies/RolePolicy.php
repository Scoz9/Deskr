<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Scrapkit\AuthorizesResources\Policies\BasePolicy;

class RolePolicy extends BasePolicy
{
    /**
     * @param  User  $user
     * @param  Role  $model
     */
    public function update(Authorizable $user, Model $model): bool
    {
        return $model->name !== 'superAdmin' && parent::update($user, $model);
    }

    /**
     * @param  User  $user
     * @param  Role  $model
     */
    public function delete(Authorizable $user, Model $model): bool
    {
        return $model->name !== 'superAdmin' && parent::delete($user, $model);
    }
}
