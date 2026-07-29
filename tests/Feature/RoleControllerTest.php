<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

test('guests are redirected to the login page', function () {
    $this->get(route('roles.index'))->assertRedirect(route('login'));
});

test('roles index is displayed to users with role:viewAny', function () {
    $this->actingAs(userWithPermissions(['role:viewAny']))
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('roles')
            ->has('roles')
            ->has('permissions')
        );
});

test('roles index is forbidden without role:viewAny', function () {
    $this->actingAs(userWithPermissions([]))
        ->get(route('roles.index'))
        ->assertForbidden();
});

test('superAdmin users can access the roles index via Gate::before', function () {
    $this->actingAs(superAdminUser())
        ->get(route('roles.index'))
        ->assertOk();
});

test('the superAdmin role is not included in the index', function () {
    Role::createOrFirst(['name' => 'superAdmin'], ['hierarchy_rank' => 0]);
    Role::createOrFirst(['name' => 'agent'], ['hierarchy_rank' => 2]);

    $this->actingAs(userWithPermissions(['role:viewAny']))
        ->get(route('roles.index'))
        ->assertInertia(fn ($page) => $page
            ->component('roles')
            ->where('roles', fn ($roles) => ! collect($roles)->pluck('name')->contains('superAdmin'))
        );
});

test('a role can be created with permissions', function () {
    createPermissions(['user:view', 'user:viewAny']);

    $this->actingAs(userWithPermissions(['role:create']))
        ->post(route('roles.store'), [
            'name' => 'redattore',
            'permissions' => ['user:view', 'user:viewAny'],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('roles.index'))
        ->assertSessionHas('flash.messages', fn (array $messages): bool => count($messages) === 1
            && $messages[0]['level'] === 'success'
            && $messages[0]['key'] === 'flash::crud.created.success'
            && ($messages[0]['context']['resourceKey'] ?? null) === 'role');

    $role = Role::where('name', 'redattore')->first();
    expect($role)->not->toBeNull()
        ->and($role->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['user:view', 'user:viewAny']);
});

test('a created role is placed at the bottom of the hierarchy', function () {
    Role::createOrFirst(['name' => 'superAdmin'], ['hierarchy_rank' => 0]);
    Role::createOrFirst(['name' => 'agent'], ['hierarchy_rank' => 2]);

    $this->actingAs(userWithPermissions(['role:create']))
        ->post(route('roles.store'), ['name' => 'redattore'])
        ->assertSessionHasNoErrors();

    expect(Role::where('name', 'redattore')->first()->hierarchy_rank)->toBe(3);
});

test('role creation requires a unique name', function () {
    Role::createOrFirst(['name' => 'redattore'], ['hierarchy_rank' => 10]);

    $this->actingAs(userWithPermissions(['role:create']))
        ->post(route('roles.store'), ['name' => 'redattore'])
        ->assertSessionHasErrors('name');
});

test('role creation is forbidden without role:create', function () {
    $this->actingAs(userWithPermissions([]))
        ->post(route('roles.store'), ['name' => 'redattore'])
        ->assertForbidden();
});

test('a role name can be updated without touching its permissions', function () {
    createPermissions(['user:view']);

    $role = Role::createOrFirst(['name' => 'redattore'], ['hierarchy_rank' => 10]);
    $role->syncPermissions(['user:view']);

    $this->actingAs(userWithPermissions(['role:update']))
        ->put(route('roles.update', $role), ['name' => 'editore'])
        ->assertSessionHasNoErrors()
        ->assertRedirect()
        ->assertSessionHas('flash.messages', fn (array $messages): bool => count($messages) === 1
            && $messages[0]['level'] === 'success'
            && $messages[0]['key'] === 'flash::crud.updated.success'
            && ($messages[0]['context']['resourceKey'] ?? null) === 'role');

    $role->refresh();
    expect($role->name)->toBe('editore')
        ->and($role->permissions->pluck('name')->all())->toBe(['user:view']);
});

test('role permissions can be synced', function () {
    createPermissions(['user:view', 'user:viewAny', 'user:update']);

    $role = Role::createOrFirst(['name' => 'redattore'], ['hierarchy_rank' => 10]);
    $role->syncPermissions(['user:view']);

    $this->actingAs(userWithPermissions(['role:update']))
        ->put(route('roles.update', $role), [
            'permissions' => ['user:viewAny', 'user:update'],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($role->refresh()->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['user:viewAny', 'user:update']);
});

test('role update rejects nonexistent permission names', function () {
    $role = Role::createOrFirst(['name' => 'redattore'], ['hierarchy_rank' => 10]);

    $this->actingAs(userWithPermissions(['role:update']))
        ->put(route('roles.update', $role), ['permissions' => ['not-a-permission']])
        ->assertSessionHasErrors('permissions.0');
});

test('role update is forbidden without role:update', function () {
    $role = Role::createOrFirst(['name' => 'redattore'], ['hierarchy_rank' => 10]);

    $this->actingAs(userWithPermissions([]))
        ->put(route('roles.update', $role), ['name' => 'editore'])
        ->assertForbidden();
});

test('the superAdmin role cannot be updated, even by superAdmin users', function () {
    $role = Role::createOrFirst(['name' => 'superAdmin'], ['hierarchy_rank' => 0]);

    $this->actingAs(superAdminUser())
        ->put(route('roles.update', $role), ['name' => 'renamed'])
        ->assertNotFound();

    expect($role->refresh()->name)->toBe('superAdmin');
});

test('a role without users can be deleted', function () {
    $role = Role::createOrFirst(['name' => 'redattore'], ['hierarchy_rank' => 10]);

    $this->actingAs(userWithPermissions(['role:delete']))
        ->delete(route('roles.destroy', $role))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('roles.index'))
        ->assertSessionHas('flash.messages', fn (array $messages): bool => count($messages) === 1
            && $messages[0]['level'] === 'success'
            && $messages[0]['key'] === 'flash::crud.deleted.success'
            && ($messages[0]['context']['resourceKey'] ?? null) === 'role');

    $this->assertDatabaseMissing('roles', ['name' => 'redattore']);
});

test('a role assigned to users cannot be deleted', function () {
    $role = Role::createOrFirst(['name' => 'redattore'], ['hierarchy_rank' => 10]);
    User::factory()->create()->assignRole($role);

    $this->actingAs(userWithPermissions(['role:delete']))
        ->delete(route('roles.destroy', $role))
        ->assertSessionHasErrors('role');

    $this->assertDatabaseHas('roles', ['name' => 'redattore']);
});

test('role deletion is forbidden without role:delete', function () {
    $role = Role::createOrFirst(['name' => 'redattore'], ['hierarchy_rank' => 10]);

    $this->actingAs(userWithPermissions([]))
        ->delete(route('roles.destroy', $role))
        ->assertForbidden();
});

test('the superAdmin role cannot be deleted, even by superAdmin users', function () {
    $role = Role::createOrFirst(['name' => 'superAdmin'], ['hierarchy_rank' => 0]);

    $this->actingAs(superAdminUser())
        ->delete(route('roles.destroy', $role))
        ->assertNotFound();

    $this->assertDatabaseHas('roles', ['name' => 'superAdmin']);
});

test('the agent role is seeded without role and permission management permissions', function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    $agent = Role::where('name', 'agent')->first();
    $admin = Role::where('name', 'admin')->first();

    expect($agent->permissions->pluck('name')
        ->filter(fn (string $name) => str_starts_with($name, 'role:') || str_starts_with($name, 'permission:'))
    )->toBeEmpty()
        ->and($admin->permissions->pluck('name'))->toContain('role:viewAny');
});
