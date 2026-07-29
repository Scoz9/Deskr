<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Scrapkit\PermissionHierarchy\Models\Role;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('the seeded roles carry their hierarchy rank', function () {
    expect(Role::where('name', 'superAdmin')->firstOrFail()->hierarchy_rank)->toBe(0)
        ->and(Role::where('name', 'amministratore')->firstOrFail()->hierarchy_rank)->toBe(1)
        ->and(Role::where('name', 'operatore')->firstOrFail()->hierarchy_rank)->toBe(2);
});

test('an amministratore can manage an operatore but not the other way around', function () {
    $amministratore = User::factory()->create()->assignRole('amministratore');
    $operatore = User::factory()->create()->assignRole('operatore');

    expect($amministratore->canManage($operatore))->toBeTrue()
        ->and($amministratore->can('users.manage', $operatore))->toBeTrue()
        ->and($operatore->canManage($amministratore))->toBeFalse()
        ->and($operatore->can('users.manage', $amministratore))->toBeFalse();
});

test('users with the same role cannot manage each other', function () {
    $first = User::factory()->create()->assignRole('operatore');
    $second = User::factory()->create()->assignRole('operatore');

    expect($first->can('users.manage', $second))->toBeFalse()
        ->and($second->can('users.manage', $first))->toBeFalse();
});

test('a user without roles cannot manage anyone but can be managed', function () {
    $withoutRoles = User::factory()->create();
    $operatore = User::factory()->create()->assignRole('operatore');

    expect($withoutRoles->hierarchyRank())->toBeNull()
        ->and($withoutRoles->can('users.manage', $operatore))->toBeFalse()
        ->and($operatore->can('users.manage', $withoutRoles))->toBeTrue();
});

test('a superAdmin bypasses the hierarchy gate via Gate::before', function () {
    $superAdmin = User::factory()->create()->assignRole('superAdmin');
    $otherSuperAdmin = User::factory()->create()->assignRole('superAdmin');

    expect($superAdmin->canManage($otherSuperAdmin))->toBeFalse()
        ->and($superAdmin->can('users.manage', $otherSuperAdmin))->toBeTrue();
});
