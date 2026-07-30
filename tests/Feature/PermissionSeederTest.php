<?php

use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;

test('the permission registry lists every model ability plus the custom permissions', function () {
    $names = PermissionSeeder::getPermissionNames();

    foreach (['user', 'role', 'organization', 'team', 'category', 'ticket'] as $model) {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            expect($names)->toContain("{$model}:{$ability}");
        }
    }

    expect($names)
        ->toContain('permission:viewAny')
        ->toContain('user:suspend')
        ->toHaveCount(36);
});

test('permission:viewAny is a custom permission, not a model permission', function () {
    expect(PermissionSeeder::getCustomPermissions())->toContain('permission:viewAny');

    expect(PermissionSeeder::getModelPermissions())
        ->not->toContain('permission:viewAny')
        ->not->toContain('user:suspend');
});

test('running the seeder creates exactly the registered permissions', function () {
    $this->seed(PermissionSeeder::class);

    $names = PermissionSeeder::getPermissionNames();

    foreach ($names as $name) {
        expect(Permission::where('name', $name)->exists())->toBeTrue();
    }

    expect(Permission::count())->toBe(count($names));
});

test('running the seeder prunes permissions that are no longer registered', function () {
    Permission::findOrCreate('stale:permission');

    $this->seed(PermissionSeeder::class);

    expect(Permission::where('name', 'stale:permission')->exists())->toBeFalse();
    expect(Permission::where('name', 'user:viewAny')->exists())->toBeTrue();
});
