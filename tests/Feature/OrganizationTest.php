<?php

use App\Models\Organization;
use Illuminate\Database\QueryException;

test('the factory persists an organization with a name', function () {
    $organization = Organization::factory()->create();

    expect($organization->name)->toBeString()->not->toBeEmpty();

    $this->assertDatabaseHas('organizations', [
        'id' => $organization->id,
        'name' => $organization->name,
    ]);
});

test('the name is mass assignable', function () {
    $organization = Organization::create(['name' => 'Acme S.p.A.']);

    expect($organization->refresh()->name)->toBe('Acme S.p.A.');
});

test('an organization cannot exist without a name', function () {
    expect(fn () => Organization::query()->create([]))->toThrow(QueryException::class);
});
