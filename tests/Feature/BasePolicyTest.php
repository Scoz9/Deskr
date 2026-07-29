<?php

use App\Models\Role;
use App\Models\User;

test('inherited viewAny grants access with the conventional permission', function () {
    $user = userWithPermissions(['user:viewAny']);

    expect($user->can('viewAny', User::class))->toBeTrue();
});

test('inherited viewAny denies access without the conventional permission', function () {
    $user = userWithPermissions([]);

    expect($user->can('viewAny', User::class))->toBeFalse();
});

test('the permission name is derived from the policy for each model', function () {
    // RolePolicy derives the "role:" prefix from its own class name.
    $user = userWithPermissions(['role:create']);

    expect($user->can('create', Role::class))->toBeTrue();
});

test('a permission for another model does not grant the ability', function () {
    // Holding user:create must not satisfy the role:create check.
    createPermissions(['user:create', 'role:create']);
    $user = User::factory()->create();
    $user->givePermissionTo('user:create');

    expect($user->can('create', Role::class))->toBeFalse();
});
