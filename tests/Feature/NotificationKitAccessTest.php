<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Scrapkit\NotificationKit\Domain\Outbox\Models\OutboxMessage;
use Scrapkit\NotificationKit\Domain\Templates\Models\NotificationTemplate;

test('a user without permissions cannot reach the notification kit api', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('notification-kit/api/v1/templates')
        ->assertForbidden();
});

test('guests cannot reach the notification kit api', function () {
    $this->getJson('notification-kit/api/v1/templates')->assertUnauthorized();
});

test('notification:viewAny opens the listing', function () {
    $this->actingAs(userWithPermissions(['notification:viewAny']))
        ->getJson('notification-kit/api/v1/templates')
        ->assertOk();
});

test('editing content requires notification:update', function () {
    $template = NotificationTemplate::factory()->create();

    $payload = ['subject' => 'Edited', 'body' => 'Body', 'requires_confirmation' => false];

    $this->actingAs(userWithPermissions(['notification:viewAny']))
        ->putJson("notification-kit/api/v1/templates/{$template->key}/content", $payload)
        ->assertForbidden();

    $this->actingAs(userWithPermissions(['notification:viewAny', 'notification:update']))
        ->putJson("notification-kit/api/v1/templates/{$template->key}/content", $payload)
        ->assertOk();
});

test('archiving requires notification:archive', function () {
    $template = NotificationTemplate::factory()->create();

    $this->actingAs(userWithPermissions(['notification:viewAny']))
        ->postJson("notification-kit/api/v1/templates/{$template->key}/archive")
        ->assertForbidden();

    $this->actingAs(userWithPermissions(['notification:viewAny', 'notification:archive']))
        ->postJson("notification-kit/api/v1/templates/{$template->key}/archive")
        ->assertOk();
});

test('approving a send requires notification:approve', function () {
    $this->actingAs(userWithPermissions(['notification:viewAny']))
        ->getJson('notification-kit/api/v1/outbox')
        ->assertOk();

    $message = OutboxMessage::factory()->create();

    $this->actingAs(userWithPermissions(['notification:viewAny']))
        ->postJson("notification-kit/api/v1/outbox/{$message->uuid}/approve")
        ->assertForbidden();

    $this->actingAs(userWithPermissions(['notification:viewAny', 'notification:approve']))
        ->postJson("notification-kit/api/v1/outbox/{$message->uuid}/approve")
        ->assertOk();
});

test('the notification permissions are part of the registry', function () {
    expect(PermissionSeeder::getCustomPermissions())
        ->toContain('notification:viewAny')
        ->toContain('notification:update')
        ->toContain('notification:archive')
        ->toContain('notification:approve');
});
