<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $allPermissions = Permission::all();

        $agentPermissions = $allPermissions->reject(
            fn (Permission $permission): bool => Str::startsWith($permission->name, ['role:', 'permission:'])
        );

        foreach (UserRole::cases() as $role) {
            Role::updateOrCreate(
                ['name' => $role->value],
                ['hierarchy_rank' => $role->hierarchyRank()],
            );
        }

        // superAdmin gets no grant: Gate::before already answers for it. A
        // requester gets none either: the portal is guarded by policies on the
        // requester's own tickets, not by abilities on the console.
        $this->role(UserRole::Admin)->syncPermissions($allPermissions);
        $this->role(UserRole::Agent)->syncPermissions($agentPermissions);
    }

    private function role(UserRole $role): Role
    {
        return Role::where('name', $role->value)->firstOrFail();
    }
}
