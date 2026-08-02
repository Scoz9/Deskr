<?php

use App\Models\Category;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('teams.index'))->assertRedirect(route('login'));
});

test('teams index is displayed to users with team:viewAny', function () {
    Team::factory()->create(['name' => 'Rete']);

    $this->actingAs(userWithPermissions(['team:viewAny']))
        ->get(route('teams.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('teams')
            ->where('teams.0.name', 'Rete')
        );
});

test('teams index is forbidden without team:viewAny', function () {
    $this->actingAs(userWithPermissions([]))
        ->get(route('teams.index'))
        ->assertForbidden();
});

test('superAdmin users can access the teams index via Gate::before', function () {
    $this->actingAs(superAdminUser())
        ->get(route('teams.index'))
        ->assertOk();
});

/*
 * The three counts are what the page says a team cannot be deleted for,
 * before anybody presses the button.
 */
test('the index counts what hangs from each team', function () {
    $team = Team::factory()->create();
    $category = Category::factory()->for($team)->create();
    $team->members()->attach(User::factory()->agent()->create());
    Ticket::factory()->for($team)->for($category)->count(2)->create();

    $this->actingAs(userWithPermissions(['team:viewAny']))
        ->get(route('teams.index'))
        ->assertInertia(fn ($page) => $page
            ->where('teams.0.categories_count', 1)
            ->where('teams.0.members_count', 1)
            ->where('teams.0.tickets_count', 2)
        );
});

test('a team can be created', function () {
    $this->actingAs(userWithPermissions(['team:create']))
        ->post(route('teams.store'), ['name' => 'Rete'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('teams.index'))
        ->assertSessionHas('flash.messages', fn (array $messages): bool => count($messages) === 1
            && $messages[0]['level'] === 'success'
            && $messages[0]['key'] === 'flash::crud.created.success'
            && ($messages[0]['context']['resourceKey'] ?? null) === 'team');

    expect(Team::where('name', 'Rete')->exists())->toBeTrue();
});

test('team creation requires a name', function () {
    $this->actingAs(userWithPermissions(['team:create']))
        ->post(route('teams.store'), [])
        ->assertSessionHasErrors('name');
});

test('team creation is forbidden without team:create', function () {
    $this->actingAs(userWithPermissions([]))
        ->post(route('teams.store'), ['name' => 'Rete'])
        ->assertForbidden();
});

test('a team can be renamed', function () {
    $team = Team::factory()->create(['name' => 'Rete']);

    $this->actingAs(userWithPermissions(['team:update']))
        ->put(route('teams.update', $team), ['name' => 'Infrastruttura'])
        ->assertSessionHasNoErrors()
        ->assertRedirect()
        ->assertSessionHas('flash.messages', fn (array $messages): bool => count($messages) === 1
            && $messages[0]['level'] === 'success'
            && $messages[0]['key'] === 'flash::crud.updated.success'
            && ($messages[0]['context']['resourceKey'] ?? null) === 'team');

    expect($team->refresh()->name)->toBe('Infrastruttura');
});

test('team update is forbidden without team:update', function () {
    $team = Team::factory()->create();

    $this->actingAs(userWithPermissions([]))
        ->put(route('teams.update', $team), ['name' => 'Infrastruttura'])
        ->assertForbidden();
});

test('a team nothing points at can be deleted', function () {
    $team = Team::factory()->create();

    $this->actingAs(userWithPermissions(['team:delete']))
        ->delete(route('teams.destroy', $team))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('teams.index'))
        ->assertSessionHas('flash.messages', fn (array $messages): bool => count($messages) === 1
            && $messages[0]['level'] === 'success'
            && $messages[0]['key'] === 'flash::crud.deleted.success'
            && ($messages[0]['context']['resourceKey'] ?? null) === 'team');

    $this->assertModelMissing($team);
});

/*
 * The database refuses both of these with a `restrictOnDelete`: the
 * controller catches them first so the refusal reaches the button that
 * asked for it instead of surfacing as a 500.
 */
test('a team with categories routed to it cannot be deleted', function () {
    $team = Team::factory()->create();
    Category::factory()->for($team)->create();

    $this->actingAs(userWithPermissions(['team:delete']))
        ->delete(route('teams.destroy', $team))
        ->assertSessionHasErrors('team');

    $this->assertModelExists($team);
});

test('a team with tickets cannot be deleted', function () {
    $team = Team::factory()->create();
    // The factory gives the ticket a category of its own, which carries its
    // own team: nothing but the ticket is holding this one.
    Ticket::factory()->for($team)->create();

    $this->actingAs(userWithPermissions(['team:delete']))
        ->delete(route('teams.destroy', $team))
        ->assertSessionHasErrors('team');

    $this->assertModelExists($team);
});

/*
 * Membership is a filter on the console and not a boundary (§4): the pivot
 * cascades, and an agent losing a team they covered is not a ticket losing
 * where it was routed.
 */
test('a team is deleted even if agents still cover it', function () {
    $team = Team::factory()->create();
    $team->members()->attach(User::factory()->agent()->create());

    $this->actingAs(userWithPermissions(['team:delete']))
        ->delete(route('teams.destroy', $team))
        ->assertSessionHasNoErrors();

    $this->assertModelMissing($team);
});

test('team deletion is forbidden without team:delete', function () {
    $team = Team::factory()->create();

    $this->actingAs(userWithPermissions([]))
        ->delete(route('teams.destroy', $team))
        ->assertForbidden();
});
