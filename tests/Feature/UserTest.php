<?php

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;

test('a user belongs to the organization they were created for', function () {
    $organization = Organization::factory()->create();

    $user = User::factory()->for($organization)->create();

    expect($user->organization->id)->toBe($organization->id)
        ->and($organization->users->pluck('id')->all())->toBe([$user->id]);
});

test('a user has no organization by default: agents and admins work for the helpdesk', function () {
    expect(User::factory()->create()->organization)->toBeNull();
});

test('deleting an organization detaches its users instead of deleting them', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->for($organization)->create();

    $organization->delete();

    expect($user->refresh()->organization_id)->toBeNull();
    $this->assertModelExists($user);
});

test('the factory states assign the matching spatie role', function (string $state, UserRole $role) {
    $user = User::factory()->{$state}()->create();

    expect($user->hasRole($role->value))->toBeTrue()
        ->and($user->getRoleNames()->all())->toBe([$role->value]);
})->with([
    'admin' => ['admin', UserRole::Admin],
    'agent' => ['agent', UserRole::Agent],
    'requester' => ['requester', UserRole::Requester],
]);
