<?php

use App\Models\User;

test('guest pages load without javascript errors', function () {
    $pages = visit(['/', '/login', '/register', '/forgot-password']);

    $pages->assertNoSmoke();
});

test('authenticated pages load without javascript errors', function () {
    $this->actingAs(userWithPermissions(['role:viewAny', 'user:viewAny']));

    $pages = visit([
        '/dashboard',
        '/roles',
        '/users',
        '/settings/profile',
        '/settings/appearance',
        '/settings/security',
    ]);

    $pages->assertNoSmoke();
});

test('users can log in from the login page', function () {
    User::factory()->create(['email' => 'vincenzo@example.com']);

    $page = visit('/');

    $page->fill('email', 'vincenzo@example.com')
        ->fill('password', 'password')
        ->click('@login-button')
        ->assertPathIs('/dashboard')
        ->assertSee('Dashboard')
        ->assertNoJavascriptErrors();

    $this->assertAuthenticated();
});
