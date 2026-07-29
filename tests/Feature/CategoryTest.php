<?php

use App\Models\Category;
use App\Models\Team;
use Illuminate\Database\QueryException;

test('the factory persists a category with a name and a destination team', function () {
    $category = Category::factory()->create();

    expect($category->name)->toBeString()->not->toBeEmpty()
        ->and($category->team)->toBeInstanceOf(Team::class);

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => $category->name,
        'team_id' => $category->team_id,
    ]);
});

test('a category cannot exist without a name', function () {
    $team = Team::factory()->create();

    expect(fn () => Category::query()->create(['team_id' => $team->id]))->toThrow(QueryException::class);
});

test('two categories cannot share the same name', function () {
    $category = Category::factory()->create();

    expect(fn () => Category::factory()->create(['name' => $category->name]))->toThrow(QueryException::class);
});

test('a category cannot exist without a destination team', function () {
    expect(fn () => Category::factory()->create(['team_id' => null]))->toThrow(QueryException::class);
});

test('a category carries its team and a team knows the categories routed to it', function () {
    $team = Team::factory()->create();
    $first = Category::factory()->for($team)->create();
    $second = Category::factory()->for($team)->create();

    expect($first->team->id)->toBe($team->id)
        ->and($team->categories->pluck('id')->all())->toBe([$first->id, $second->id]);
});

/**
 * No assertion on the surviving rows: on PostgreSQL the foreign key violation
 * aborts the surrounding transaction, so any further query in the test fails.
 * The refused delete is the whole point.
 */
test('a team with categories cannot be deleted', function () {
    $team = Team::factory()->create();
    Category::factory()->for($team)->create();

    expect(fn () => $team->delete())->toThrow(QueryException::class);
});
