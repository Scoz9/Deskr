<?php

use App\Models\Category;
use App\Models\Team;
use App\Models\Ticket;

test('guests are redirected to the login page', function () {
    $this->get(route('categories.index'))->assertRedirect(route('login'));
});

test('categories index is displayed to users with category:viewAny', function () {
    $team = Team::factory()->create(['name' => 'Rete']);
    Category::factory()->for($team)->create(['name' => 'Accessi']);

    $this->actingAs(userWithPermissions(['category:viewAny']))
        ->get(route('categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('categories')
            ->where('categories.0.name', 'Accessi')
            ->where('categories.0.team.name', 'Rete')
            ->where('categories.0.tickets_count', 0)
        );
});

test('categories index is forbidden without category:viewAny', function () {
    $this->actingAs(userWithPermissions([]))
        ->get(route('categories.index'))
        ->assertForbidden();
});

test('superAdmin users can access the categories index via Gate::before', function () {
    $this->actingAs(superAdminUser())
        ->get(route('categories.index'))
        ->assertOk();
});

/*
 * A category cannot exist without a team, so the form has nothing to offer
 * unless the teams travel with the page.
 */
test('the index offers the teams a category may route to, by name', function () {
    Team::factory()->create(['name' => 'Rete']);
    Team::factory()->create(['name' => 'Applicativi']);

    $this->actingAs(userWithPermissions(['category:viewAny']))
        ->get(route('categories.index'))
        ->assertInertia(fn ($page) => $page
            ->where('teams', fn ($teams) => collect($teams)->pluck('name')->all() === ['Applicativi', 'Rete'])
        );
});

test('a category can be created, routed to a team', function () {
    $team = Team::factory()->create();

    $this->actingAs(userWithPermissions(['category:create']))
        ->post(route('categories.store'), ['name' => 'Accessi', 'team_id' => $team->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categories.index'))
        ->assertSessionHas('flash.messages', fn (array $messages): bool => count($messages) === 1
            && $messages[0]['level'] === 'success'
            && $messages[0]['key'] === 'flash::crud.created.success'
            && ($messages[0]['context']['resourceKey'] ?? null) === 'category');

    expect(Category::sole())
        ->name->toBe('Accessi')
        ->team_id->toBe($team->id);
});

/*
 * Unlike a team's or an organization's, this name carries a unique index:
 * the public intake shows the list to whoever is asking for help, and two
 * rows reading the same would be a choice nobody can make.
 */
test('category creation requires a unique name', function () {
    Category::factory()->create(['name' => 'Accessi']);

    $this->actingAs(userWithPermissions(['category:create']))
        ->post(route('categories.store'), [
            'name' => 'Accessi',
            'team_id' => Team::factory()->create()->id,
        ])
        ->assertSessionHasErrors('name');
});

test('category creation requires a team', function () {
    $this->actingAs(userWithPermissions(['category:create']))
        ->post(route('categories.store'), ['name' => 'Accessi'])
        ->assertSessionHasErrors('team_id');

    expect(Category::query()->exists())->toBeFalse();
});

test('a team that does not exist is refused', function () {
    $this->actingAs(userWithPermissions(['category:create']))
        ->post(route('categories.store'), ['name' => 'Accessi', 'team_id' => 99999])
        ->assertSessionHasErrors('team_id');
});

test('category creation is forbidden without category:create', function () {
    $this->actingAs(userWithPermissions([]))
        ->post(route('categories.store'), [
            'name' => 'Accessi',
            'team_id' => Team::factory()->create()->id,
        ])
        ->assertForbidden();
});

test('a category can be renamed', function () {
    $category = Category::factory()->create(['name' => 'Accessi']);

    $this->actingAs(userWithPermissions(['category:update']))
        ->put(route('categories.update', $category), [
            'name' => 'Credenziali',
            'team_id' => $category->team_id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect()
        ->assertSessionHas('flash.messages', fn (array $messages): bool => count($messages) === 1
            && $messages[0]['level'] === 'success'
            && $messages[0]['key'] === 'flash::crud.updated.success'
            && ($messages[0]['context']['resourceKey'] ?? null) === 'category');

    expect($category->refresh()->name)->toBe('Credenziali');
});

test('a category keeping its own name is not refused as a duplicate', function () {
    $category = Category::factory()->create(['name' => 'Accessi']);
    $other = Team::factory()->create();

    $this->actingAs(userWithPermissions(['category:update']))
        ->put(route('categories.update', $category), [
            'name' => 'Accessi',
            'team_id' => $other->id,
        ])
        ->assertSessionHasNoErrors();

    expect($category->refresh()->team_id)->toBe($other->id);
});

test('a name another category already holds is refused', function () {
    Category::factory()->create(['name' => 'Accessi']);
    $category = Category::factory()->create(['name' => 'Rete']);

    $this->actingAs(userWithPermissions(['category:update']))
        ->put(route('categories.update', $category), [
            'name' => 'Accessi',
            'team_id' => $category->team_id,
        ])
        ->assertSessionHasErrors('name');
});

/*
 * The intake writes `team_id` on the ticket itself (§4): re-routing decides
 * where the next ones go and rewrites nothing behind it.
 */
test('re-routing a category leaves the tickets already filed where they went', function () {
    $category = Category::factory()->create();
    $originalTeam = $category->team_id;
    $ticket = Ticket::factory()->for($category)->create(['team_id' => $originalTeam]);
    $other = Team::factory()->create();

    $this->actingAs(userWithPermissions(['category:update']))
        ->put(route('categories.update', $category), [
            'name' => $category->name,
            'team_id' => $other->id,
        ])
        ->assertSessionHasNoErrors();

    expect($category->refresh()->team_id)->toBe($other->id)
        ->and($ticket->refresh()->team_id)->toBe($originalTeam);
});

test('category update is forbidden without category:update', function () {
    $category = Category::factory()->create();

    $this->actingAs(userWithPermissions([]))
        ->put(route('categories.update', $category), [
            'name' => 'Credenziali',
            'team_id' => $category->team_id,
        ])
        ->assertForbidden();
});

test('a category with no tickets can be deleted', function () {
    $category = Category::factory()->create();

    $this->actingAs(userWithPermissions(['category:delete']))
        ->delete(route('categories.destroy', $category))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categories.index'))
        ->assertSessionHas('flash.messages', fn (array $messages): bool => count($messages) === 1
            && $messages[0]['level'] === 'success'
            && $messages[0]['key'] === 'flash::crud.deleted.success'
            && ($messages[0]['context']['resourceKey'] ?? null) === 'category');

    $this->assertModelMissing($category);
});

/*
 * The database refuses this with a `restrictOnDelete`: the controller
 * catches it first so the refusal reaches the button that asked for it
 * instead of surfacing as a 500.
 */
test('a category with tickets cannot be deleted', function () {
    $category = Category::factory()->create();
    Ticket::factory()->for($category)->create();

    $this->actingAs(userWithPermissions(['category:delete']))
        ->delete(route('categories.destroy', $category))
        ->assertSessionHasErrors('category');

    $this->assertModelExists($category);
});

test('category deletion is forbidden without category:delete', function () {
    $category = Category::factory()->create();

    $this->actingAs(userWithPermissions([]))
        ->delete(route('categories.destroy', $category))
        ->assertForbidden();
});
