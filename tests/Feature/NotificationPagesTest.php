<?php

use Inertia\Testing\AssertableInertia;

test('the notifications page requires the view permission', function () {
    $this->actingAs(userWithPermissions([]))
        ->get(route('notifications.index'))
        ->assertForbidden();
});

test('the notifications page renders for a permitted user', function () {
    $this->actingAs(userWithPermissions(['notification:viewAny']))
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('notifications'));
});

test('the outbox page renders for a permitted user', function () {
    $this->actingAs(userWithPermissions(['notification:viewAny']))
        ->get(route('notifications.outbox'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('notifications/outbox'));
});

test('guests are redirected to the login page', function () {
    $this->get(route('notifications.index'))->assertRedirect(route('login'));
});
