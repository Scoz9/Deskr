<?php

use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('quick login authenticates a user with the requested role', function () {
    $role = Role::create(['name' => 'superAdmin', 'hierarchy_rank' => 0]);
    $user = User::factory()->create();
    $user->assignRole($role);

    $response = $this->post(route('quick-login.store', ['role' => 'superAdmin']));

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('quick login returns 404 when no user has the role', function () {
    Role::create(['name' => 'superAdmin', 'hierarchy_rank' => 0]);

    $this->post(route('quick-login.store', ['role' => 'superAdmin']))->assertNotFound();

    $this->assertGuest();
});

test('login screen receives the quick login roles ordered by hierarchy rank', function () {
    Role::create(['name' => 'operatore', 'hierarchy_rank' => 2]);
    Role::create(['name' => 'superAdmin', 'hierarchy_rank' => 0]);
    Role::create(['name' => 'amministratore', 'hierarchy_rank' => 1]);

    $this->get(route('login'))->assertInertia(
        fn (Assert $page) => $page
            ->component('auth/login')
            ->has('quickLogin.roles', 3)
            ->where('quickLogin.roles.0.name', 'superAdmin')
            ->where('quickLogin.roles.1.name', 'amministratore')
            ->where('quickLogin.roles.2.name', 'operatore'),
    );
});
