<?php

use App\Enums\UserRole;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

test('it seeds exactly the roles listed by the enum, each with its hierarchy rank', function () {
    expect(Role::pluck('hierarchy_rank', 'name')->all())->toEqual([
        'superAdmin' => 0,
        'admin' => 1,
        'agent' => 2,
        'requester' => 3,
    ]);
});

test('an admin holds every permission', function () {
    $admin = Role::where('name', UserRole::Admin->value)->firstOrFail();

    expect($admin->permissions->pluck('name')->all())
        ->toBe(PermissionSeeder::getPermissionNames());
});

test('an agent holds every permission except the ones that govern roles and permissions', function () {
    $agent = Role::where('name', UserRole::Agent->value)->firstOrFail();
    $granted = $agent->permissions->pluck('name');

    expect($granted)->not->toBeEmpty()
        ->and($granted->filter(fn (string $name): bool => str_starts_with($name, 'role:'))->all())->toBeEmpty()
        ->and($granted->filter(fn (string $name): bool => str_starts_with($name, 'permission:'))->all())->toBeEmpty()
        ->and($granted->all())->toContain('user:viewAny', 'organization:viewAny');
});

test('a requester holds no permission: the portal is guarded by policies, not by grants', function () {
    $requester = Role::where('name', UserRole::Requester->value)->firstOrFail();

    expect($requester->permissions)->toBeEmpty();
});

test('superAdmin holds no permission either: Gate::before grants it everything', function () {
    $superAdmin = Role::where('name', UserRole::SuperAdmin->value)->firstOrFail();

    expect($superAdmin->permissions)->toBeEmpty();
});
