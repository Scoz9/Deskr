<?php

namespace App\Concerns;

use App\Models\Role;
use Closure;

trait ValidatesAssignableRoles
{
    /**
     * Get the rule ensuring the acting user may assign the given role.
     *
     * A role is assignable only when it is not superAdmin and its rank is
     * strictly worse (greater) than the acting user's rank. Validation is not
     * bypassed by Gate::before, so this also stops superAdmins from assigning
     * superAdmin.
     */
    protected function assignableRoleRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $actorRank = $this->user()->hierarchyRank();
            $roleRank = Role::query()->where('name', $value)->value('hierarchy_rank');

            if ($value === 'superAdmin' || $actorRank === null || $roleRank === null || $roleRank <= $actorRank) {
                $fail(__('You cannot assign this role.'));
            }
        };
    }
}
