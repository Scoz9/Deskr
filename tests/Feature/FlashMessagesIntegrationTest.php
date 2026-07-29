<?php

use App\Models\Role;

/**
 * End-to-end coverage of scrapkit/laravel-flash-messages: a CRUD action flashes
 * a convention-based message that is shared to the frontend as the resolved
 * `flashMessages` Inertia prop (structural key + server-rendered text).
 */
test('a created resource is shared as a resolved flashMessages inertia prop', function () {
    createPermissions(['role:create', 'role:viewAny']);
    $actor = userWithPermissions(['role:create', 'role:viewAny']);

    $this->actingAs($actor)->post(route('roles.store'), ['name' => 'redattore']);

    $this->actingAs($actor)
        ->get(route('roles.index'))
        ->assertInertia(fn ($page) => $page
            ->has('flashMessages', 1)
            ->where('flashMessages.0.level', 'success')
            ->where('flashMessages.0.key', 'flash::crud.created.success')
            ->where('flashMessages.0.context.resourceKey', 'role')
            ->where('flashMessages.0.message', 'Role created successfully.')
        );
});

test('a deleted resource is shared as a resolved flashMessages inertia prop', function () {
    createPermissions(['role:delete', 'role:viewAny']);
    $actor = userWithPermissions(['role:delete', 'role:viewAny']);
    $role = Role::createOrFirst(['name' => 'redattore'], ['hierarchy_rank' => 10]);

    $this->actingAs($actor)->delete(route('roles.destroy', $role));

    $this->actingAs($actor)
        ->get(route('roles.index'))
        ->assertInertia(fn ($page) => $page
            ->has('flashMessages', 1)
            ->where('flashMessages.0.key', 'flash::crud.deleted.success')
            ->where('flashMessages.0.message', 'Role deleted successfully.')
        );
});

test('pages without a flash carry an empty flashMessages prop', function () {
    $actor = userWithPermissions(['role:viewAny']);

    $this->actingAs($actor)
        ->get(route('roles.index'))
        ->assertInertia(fn ($page) => $page->where('flashMessages', []));
});
