<?php

use App\Models\Role;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * Create a user assigned to a role with the given hierarchy rank.
 *
 * userWithPermissions() creates users without roles, whose null rank makes
 * canManage() always deny — use this helper for hierarchy happy paths.
 */
function rankedUser(int $rank, array $permissions = []): User
{
    $role = Role::createOrFirst(['name' => "role-rank-{$rank}"], ['hierarchy_rank' => $rank]);

    createPermissions($permissions);
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('guests are redirected to the login page', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});

test('users index is displayed to users with user:viewAny', function () {
    $this->actingAs(userWithPermissions(['user:viewAny']))
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('users')
            ->has('users')
            ->has('roles')
        );
});

test('users index is forbidden without user:viewAny', function () {
    $this->actingAs(userWithPermissions([]))
        ->get(route('users.index'))
        ->assertForbidden();
});

test('superAdmin users can access the users index via Gate::before', function () {
    $this->actingAs(superAdminUser())
        ->get(route('users.index'))
        ->assertOk();
});

test('superAdmin users are not included in the index', function () {
    $superAdmin = superAdminUser();

    $this->actingAs(userWithPermissions(['user:viewAny']))
        ->get(route('users.index'))
        ->assertInertia(fn ($page) => $page
            ->component('users')
            ->where('users', fn ($users) => ! collect($users)->pluck('email')->contains($superAdmin->email))
        );
});

test('only roles ranked below the acting user are assignable, ordered by hierarchy', function () {
    Role::createOrFirst(['name' => 'superAdmin'], ['hierarchy_rank' => 0]);
    Role::createOrFirst(['name' => 'redattore'], ['hierarchy_rank' => 10]);
    Role::createOrFirst(['name' => 'vicedirettore'], ['hierarchy_rank' => 5]);

    $this->actingAs(rankedUser(1, ['user:viewAny']))
        ->get(route('users.index'))
        ->assertInertia(fn ($page) => $page
            ->component('users')
            ->where('roles', fn ($roles) => collect($roles)->pluck('name')->all() === ['vicedirettore', 'redattore'])
        );
});

test('can_update and can_suspend reflect the role hierarchy', function () {
    $actor = rankedUser(1, ['user:viewAny', 'user:update', 'user:suspend']);
    $lowerRanked = rankedUser(10);
    $sameRanked = User::factory()->create();
    $sameRanked->assignRole('role-rank-1');

    $flagsFor = fn (array $users, User $user): array => collect($users)->firstWhere('id', $user->id);

    $this->actingAs($actor)
        ->get(route('users.index'))
        ->assertInertia(fn ($page) => $page
            ->component('users')
            ->where('users', function ($users) use ($flagsFor, $actor, $lowerRanked, $sameRanked) {
                $users = collect($users)->all();

                return $flagsFor($users, $lowerRanked)['can_update'] === true
                    && $flagsFor($users, $lowerRanked)['can_suspend'] === true
                    && $flagsFor($users, $sameRanked)['can_update'] === false
                    && $flagsFor($users, $sameRanked)['can_suspend'] === false
                    && $flagsFor($users, $actor)['can_update'] === false
                    && $flagsFor($users, $actor)['can_suspend'] === false;
            })
        );
});

test('a user can be created with a role and receives an invitation to set the password', function () {
    Notification::fake();
    Role::createOrFirst(['name' => 'redattore'], ['hierarchy_rank' => 10]);

    $this->actingAs(rankedUser(1, ['user:create']))
        ->post(route('users.store'), [
            'name' => 'Mario Rossi',
            'email' => 'mario.rossi@example.com',
            'role' => 'redattore',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('flash.messages', fn (array $messages): bool => count($messages) === 1
            && $messages[0]['level'] === 'success'
            && $messages[0]['key'] === 'flash::crud.created.success'
            && ($messages[0]['context']['resourceKey'] ?? null) === 'user');

    $user = User::where('email', 'mario.rossi@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->hasRole('redattore'))->toBeTrue()
        ->and($user->email_verified_at)->toBeNull();

    Notification::assertSentTo($user, UserInvitation::class);
    Notification::assertNotSentTo($user, VerifyEmail::class);
});

test('a submitted password is ignored on creation', function () {
    Notification::fake();
    Role::createOrFirst(['name' => 'redattore'], ['hierarchy_rank' => 10]);

    $this->actingAs(rankedUser(1, ['user:create']))
        ->post(route('users.store'), [
            'name' => 'Mario Rossi',
            'email' => 'mario.rossi@example.com',
            'password' => 'password-scelta-da-altri',
            'role' => 'redattore',
        ])
        ->assertSessionHasNoErrors();

    $user = User::where('email', 'mario.rossi@example.com')->first();
    expect(Hash::check('password-scelta-da-altri', $user->password))->toBeFalse();
});

test('user creation requires a role', function () {
    $this->actingAs(rankedUser(1, ['user:create']))
        ->post(route('users.store'), [
            'name' => 'Mario Rossi',
            'email' => 'mario.rossi@example.com',
        ])
        ->assertSessionHasErrors('role');

    expect(User::where('email', 'mario.rossi@example.com')->exists())->toBeFalse();
});

test('user creation requires a unique email', function () {
    User::factory()->create(['email' => 'mario.rossi@example.com']);

    $this->actingAs(rankedUser(1, ['user:create']))
        ->post(route('users.store'), [
            'name' => 'Mario Rossi',
            'email' => 'mario.rossi@example.com',
        ])
        ->assertSessionHasErrors('email');
});

test('a role with equal or better rank cannot be assigned', function (int $roleRank) {
    Role::createOrFirst(['name' => 'capo'], ['hierarchy_rank' => $roleRank]);

    $this->actingAs(rankedUser(10, ['user:create']))
        ->post(route('users.store'), [
            'name' => 'Mario Rossi',
            'email' => 'mario.rossi@example.com',
            'role' => 'capo',
        ])
        ->assertSessionHasErrors('role');
})->with([10, 1]);

test('user creation is forbidden without user:create', function () {
    $this->actingAs(userWithPermissions([]))
        ->post(route('users.store'), [
            'name' => 'Mario Rossi',
            'email' => 'mario.rossi@example.com',
        ])
        ->assertForbidden();
});

test('the superAdmin role cannot be assigned, even by superAdmin users', function () {
    $this->actingAs(superAdminUser())
        ->post(route('users.store'), [
            'name' => 'Mario Rossi',
            'email' => 'mario.rossi@example.com',
            'role' => 'superAdmin',
        ])
        ->assertSessionHasErrors('role');
});

test('a user can be updated with a new role', function () {
    Role::createOrFirst(['name' => 'redattore'], ['hierarchy_rank' => 5]);
    $target = rankedUser(10);

    $this->actingAs(rankedUser(1, ['user:update']))
        ->put(route('users.update', $target), [
            'name' => 'Nuovo Nome',
            'email' => 'nuova.email@example.com',
            'role' => 'redattore',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect()
        ->assertSessionHas('flash.messages', fn (array $messages): bool => count($messages) === 1
            && $messages[0]['level'] === 'success'
            && $messages[0]['key'] === 'flash::crud.updated.success'
            && ($messages[0]['context']['resourceKey'] ?? null) === 'user');

    $target->refresh();
    expect($target->name)->toBe('Nuovo Nome')
        ->and($target->email)->toBe('nuova.email@example.com')
        ->and($target->hasRole('redattore'))->toBeTrue()
        ->and($target->hasRole('role-rank-10'))->toBeFalse();
});

test('the password is not changed when left empty', function () {
    $target = rankedUser(10);

    $this->actingAs(rankedUser(1, ['user:update']))
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => '',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('password', $target->refresh()->password))->toBeTrue();
});

test('the password is changed when filled', function () {
    $target = rankedUser(10);

    $this->actingAs(rankedUser(1, ['user:update']))
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => 'una-nuova-password',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('una-nuova-password', $target->refresh()->password))->toBeTrue();
});

test('a user cannot update a target with equal or better rank', function (int $targetRank) {
    $target = rankedUser($targetRank);

    $this->actingAs(rankedUser(10, ['user:update']))
        ->put(route('users.update', $target), [
            'name' => 'Nuovo Nome',
            'email' => 'nuova.email@example.com',
        ])
        ->assertForbidden();
})->with([10, 1]);

test('a user without roles cannot update anyone, even with user:update', function () {
    $target = rankedUser(10);

    $this->actingAs(userWithPermissions(['user:update']))
        ->put(route('users.update', $target), [
            'name' => 'Nuovo Nome',
            'email' => 'nuova.email@example.com',
        ])
        ->assertForbidden();
});

test('superAdmin users cannot be updated, even by superAdmin users', function () {
    $target = superAdminUser();

    $this->actingAs(superAdminUser())
        ->put(route('users.update', $target), [
            'name' => 'Nuovo Nome',
            'email' => 'nuova.email@example.com',
        ])
        ->assertNotFound();
});

test('a user can be suspended permanently', function () {
    $target = rankedUser(10);

    $this->actingAs(rankedUser(1, ['user:suspend']))
        ->post(route('users.suspend', $target))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $target->refresh();
    expect($target->suspended_at)->not->toBeNull()
        ->and($target->isSuspended())->toBeTrue();
});

test('a user can be suspended until a future date', function () {
    $target = rankedUser(10);

    $this->actingAs(rankedUser(1, ['user:suspend']))
        ->post(route('users.suspend', $target), [
            'suspended_until' => now()->addWeek()->format('Y-m-d\TH:i'),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $target->refresh();
    expect($target->suspended_at)->toBeNull()
        ->and($target->suspended_until)->not->toBeNull()
        ->and($target->isSuspended())->toBeTrue();
});

test('a suspension date in the past is rejected', function () {
    $target = rankedUser(10);

    $this->actingAs(rankedUser(1, ['user:suspend']))
        ->post(route('users.suspend', $target), [
            'suspended_until' => now()->subDay()->format('Y-m-d\TH:i'),
        ])
        ->assertSessionHasErrors('suspended_until');

    expect($target->refresh()->isSuspended())->toBeFalse();
});

test('users cannot suspend themselves', function () {
    $actor = rankedUser(1, ['user:suspend']);

    $this->actingAs($actor)
        ->post(route('users.suspend', $actor))
        ->assertForbidden();

    expect($actor->refresh()->isSuspended())->toBeFalse();
});

test('a user cannot suspend a target with equal or better rank', function (int $targetRank) {
    $target = rankedUser($targetRank);

    $this->actingAs(rankedUser(10, ['user:suspend']))
        ->post(route('users.suspend', $target))
        ->assertForbidden();
})->with([10, 1]);

test('user suspension is forbidden without user:suspend', function () {
    $target = rankedUser(10);

    $this->actingAs(rankedUser(1, ['user:update']))
        ->post(route('users.suspend', $target))
        ->assertForbidden();
});

test('superAdmin users cannot be suspended, even by superAdmin users', function () {
    $target = superAdminUser();

    $this->actingAs(superAdminUser())
        ->post(route('users.suspend', $target))
        ->assertNotFound();
});

test('a suspension can be lifted', function () {
    $target = rankedUser(10);
    $target->suspend();
    $target->suspendUntil(now()->addWeek());

    $this->actingAs(rankedUser(1, ['user:suspend']))
        ->delete(route('users.unsuspend', $target))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $target->refresh();
    expect($target->suspended_at)->toBeNull()
        ->and($target->suspended_until)->toBeNull()
        ->and($target->isSuspended())->toBeFalse();
});

test('lifting a suspension is forbidden without user:suspend', function () {
    $target = rankedUser(10);
    $target->suspend();

    $this->actingAs(rankedUser(1, ['user:update']))
        ->delete(route('users.unsuspend', $target))
        ->assertForbidden();

    expect($target->refresh()->isSuspended())->toBeTrue();
});
