<?php

use App\Models\Organization;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('organizations.index'))->assertRedirect(route('login'));
});

test('organizations index is displayed to users with organization:viewAny', function () {
    Organization::factory()->create(['name' => 'Acme SRL']);

    $this->actingAs(userWithPermissions(['organization:viewAny']))
        ->get(route('organizations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organizations')
            ->where('organizations.0.name', 'Acme SRL')
            ->where('organizations.0.users_count', 0)
        );
});

test('organizations index is forbidden without organization:viewAny', function () {
    $this->actingAs(userWithPermissions([]))
        ->get(route('organizations.index'))
        ->assertForbidden();
});

test('superAdmin users can access the organizations index via Gate::before', function () {
    $this->actingAs(superAdminUser())
        ->get(route('organizations.index'))
        ->assertOk();
});

test('the index counts the users belonging to each organization', function () {
    $organization = Organization::factory()->create();
    User::factory()->for($organization)->count(2)->create();

    $this->actingAs(userWithPermissions(['organization:viewAny']))
        ->get(route('organizations.index'))
        ->assertInertia(fn ($page) => $page
            ->where('organizations.0.users_count', 2)
        );
});

test('an organization can be created', function () {
    $this->actingAs(userWithPermissions(['organization:create']))
        ->post(route('organizations.store'), ['name' => 'Acme SRL'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('organizations.index'))
        ->assertSessionHas('flash.messages', fn (array $messages): bool => count($messages) === 1
            && $messages[0]['level'] === 'success'
            && $messages[0]['key'] === 'flash::crud.created.success'
            && ($messages[0]['context']['resourceKey'] ?? null) === 'organization');

    expect(Organization::where('name', 'Acme SRL')->exists())->toBeTrue();
});

/*
 * Unlike a role's name, an organization's is not an identifier the
 * permission system reads — two real companies sharing a name is not
 * something the helpdesk needs to refuse.
 */
test('two organizations may share the same name', function () {
    Organization::factory()->create(['name' => 'Acme SRL']);

    $this->actingAs(userWithPermissions(['organization:create']))
        ->post(route('organizations.store'), ['name' => 'Acme SRL'])
        ->assertSessionHasNoErrors();

    expect(Organization::where('name', 'Acme SRL')->count())->toBe(2);
});

test('organization creation requires a name', function () {
    $this->actingAs(userWithPermissions(['organization:create']))
        ->post(route('organizations.store'), [])
        ->assertSessionHasErrors('name');
});

test('organization creation is forbidden without organization:create', function () {
    $this->actingAs(userWithPermissions([]))
        ->post(route('organizations.store'), ['name' => 'Acme SRL'])
        ->assertForbidden();
});

test('an organization can be renamed', function () {
    $organization = Organization::factory()->create(['name' => 'Acme SRL']);

    $this->actingAs(userWithPermissions(['organization:update']))
        ->put(route('organizations.update', $organization), ['name' => 'Acme SPA'])
        ->assertSessionHasNoErrors()
        ->assertRedirect()
        ->assertSessionHas('flash.messages', fn (array $messages): bool => count($messages) === 1
            && $messages[0]['level'] === 'success'
            && $messages[0]['key'] === 'flash::crud.updated.success'
            && ($messages[0]['context']['resourceKey'] ?? null) === 'organization');

    expect($organization->refresh()->name)->toBe('Acme SPA');
});

test('organization update is forbidden without organization:update', function () {
    $organization = Organization::factory()->create();

    $this->actingAs(userWithPermissions([]))
        ->put(route('organizations.update', $organization), ['name' => 'Acme SPA'])
        ->assertForbidden();
});

test('an organization without users can be deleted', function () {
    $organization = Organization::factory()->create();

    $this->actingAs(userWithPermissions(['organization:delete']))
        ->delete(route('organizations.destroy', $organization))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('organizations.index'))
        ->assertSessionHas('flash.messages', fn (array $messages): bool => count($messages) === 1
            && $messages[0]['level'] === 'success'
            && $messages[0]['key'] === 'flash::crud.deleted.success'
            && ($messages[0]['context']['resourceKey'] ?? null) === 'organization');

    $this->assertModelMissing($organization);
});

test('an organization with users cannot be deleted', function () {
    $organization = Organization::factory()->create();
    User::factory()->for($organization)->create();

    $this->actingAs(userWithPermissions(['organization:delete']))
        ->delete(route('organizations.destroy', $organization))
        ->assertSessionHasErrors('organization');

    $this->assertModelExists($organization);
});

test('organization deletion is forbidden without organization:delete', function () {
    $organization = Organization::factory()->create();

    $this->actingAs(userWithPermissions([]))
        ->delete(route('organizations.destroy', $organization))
        ->assertForbidden();
});
