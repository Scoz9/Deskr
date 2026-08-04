<?php

use App\Models\User;

test('guest pages load without javascript errors', function () {
    $pages = visit(['/', '/login', '/forgot-password']);

    $pages->assertNoSmoke();
});

test('authenticated pages load without javascript errors', function () {
    $this->actingAs(userWithPermissions([
        'role:viewAny',
        'user:viewAny',
        'organization:viewAny',
        'team:viewAny',
        'category:viewAny',
    ]));

    $pages = visit([
        '/dashboard',
        '/roles',
        '/users',
        '/organizations',
        '/teams',
        '/categories',
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
