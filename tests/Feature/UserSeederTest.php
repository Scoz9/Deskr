<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('it creates one user per role with the matching role assigned', function () {
    $this->seed(UserSeeder::class);

    expect(User::count())->toBe(4);

    $expected = [
        'super-admin@example.test' => ['name' => 'Super Admin', 'role' => 'superAdmin'],
        'admin@example.test' => ['name' => 'Admin', 'role' => 'admin'],
        'agent@example.test' => ['name' => 'Agent', 'role' => 'agent'],
        'requester@example.test' => ['name' => 'Requester', 'role' => 'requester'],
    ];

    foreach ($expected as $email => $data) {
        $user = User::where('email', $email)->first();

        expect($user)->not->toBeNull()
            ->and($user->name)->toBe($data['name'])
            ->and($user->hasRole($data['role']))->toBeTrue();
    }
});

test('it is idempotent when run twice', function () {
    $this->seed(UserSeeder::class);
    $this->seed(UserSeeder::class);

    expect(User::count())->toBe(4);
});

test('seeded users get a random password outside local', function () {
    // Tests run in the "testing" environment, which is not local.
    $this->seed(UserSeeder::class);

    $password = User::where('email', 'agent@example.test')->value('password');

    expect(Hash::check('password', $password))->toBeFalse();
});

test('seeded users get the well-known password in local', function () {
    $this->app['env'] = 'local';

    $this->seed(UserSeeder::class);

    $password = User::where('email', 'agent@example.test')->value('password');

    expect(Hash::check('password', $password))->toBeTrue();
});
