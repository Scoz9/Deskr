<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;

test('the factory persists a team with a name', function () {
    $team = Team::factory()->create();

    expect($team->name)->toBeString()->not->toBeEmpty();

    $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => $team->name]);
});

test('a team cannot exist without a name', function () {
    expect(fn () => Team::query()->create([]))->toThrow(QueryException::class);
});

test('agents belong to a team and a team knows its members', function () {
    $team = Team::factory()->create();
    $first = User::factory()->agent()->create();
    $second = User::factory()->agent()->create();

    $team->members()->attach([$first->id, $second->id]);

    expect($team->members->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($first->teams->pluck('id')->all())->toBe([$team->id]);
});

test('an agent can cover more than one team', function () {
    $agent = User::factory()->agent()->create();
    $teams = Team::factory()->count(2)->create();

    $agent->teams()->attach($teams);

    expect($agent->teams)->toHaveCount(2);
});

test('the same agent cannot be added to the same team twice', function () {
    $team = Team::factory()->create();
    $agent = User::factory()->agent()->create();

    $team->members()->attach($agent);

    expect(fn () => $team->members()->attach($agent))->toThrow(QueryException::class);
});

test('deleting a team drops its memberships and leaves the agents alone', function () {
    $team = Team::factory()->create();
    $agent = User::factory()->agent()->create();
    $team->members()->attach($agent);

    $team->delete();

    $this->assertDatabaseEmpty('team_user');
    $this->assertModelExists($agent);
});

test('deleting an agent drops their memberships and leaves the team alone', function () {
    $team = Team::factory()->create();
    $agent = User::factory()->agent()->create();
    $team->members()->attach($agent);

    $agent->delete();

    $this->assertDatabaseEmpty('team_user');
    $this->assertModelExists($team);
});
