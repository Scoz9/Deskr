<?php

namespace Database\Seeders;

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

        $operatorePermissions = $allPermissions->reject(
            fn (Permission $permission): bool => Str::startsWith($permission->name, ['role:', 'permission:'])
        );

        Role::updateOrCreate(['name' => 'superAdmin'], ['hierarchy_rank' => 0]);
        Role::updateOrCreate(['name' => 'amministratore'], ['hierarchy_rank' => 1])->syncPermissions($allPermissions);
        Role::updateOrCreate(['name' => 'operatore'], ['hierarchy_rank' => 2])->syncPermissions($operatorePermissions);
    }
}
