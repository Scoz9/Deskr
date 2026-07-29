<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Scrapkit\PermissionHierarchy\Models\Role;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('the seeded roles carry their hierarchy rank', function () {
    expect(Role::where('name', 'superAdmin')->firstOrFail()->hierarchy_rank)->toBe(0)
        ->and(Role::where('name', 'admin')->firstOrFail()->hierarchy_rank)->toBe(1)
        ->and(Role::where('name', 'agent')->firstOrFail()->hierarchy_rank)->toBe(2);
});

test('an admin can manage an agent but not the other way around', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $agent = User::factory()->create()->assignRole('agent');

    expect($admin->canManage($agent))->toBeTrue()
        ->and($admin->can('users.manage', $agent))->toBeTrue()
        ->and($agent->canManage($admin))->toBeFalse()
        ->and($agent->can('users.manage', $admin))->toBeFalse();
});

test('users with the same role cannot manage each other', function () {
    $first = User::factory()->create()->assignRole('agent');
    $second = User::factory()->create()->assignRole('agent');

    expect($first->can('users.manage', $second))->toBeFalse()
        ->and($second->can('users.manage', $first))->toBeFalse();
});

test('a user without roles cannot manage anyone but can be managed', function () {
    $withoutRoles = User::factory()->create();
    $agent = User::factory()->create()->assignRole('agent');

    expect($withoutRoles->hierarchyRank())->toBeNull()
        ->and($withoutRoles->can('users.manage', $agent))->toBeFalse()
        ->and($agent->can('users.manage', $withoutRoles))->toBeTrue();
});

test('a superAdmin bypasses the hierarchy gate via Gate::before', function () {
    $superAdmin = User::factory()->create()->assignRole('superAdmin');
    $otherSuperAdmin = User::factory()->create()->assignRole('superAdmin');

    expect($superAdmin->canManage($otherSuperAdmin))->toBeFalse()
        ->and($superAdmin->can('users.manage', $otherSuperAdmin))->toBeTrue();
});
