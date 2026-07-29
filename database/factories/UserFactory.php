<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Give the user the admin role, creating it if the seeder has not run.
     */
    public function admin(): static
    {
        return $this->withRole(UserRole::Admin);
    }

    /**
     * Give the user the agent role, creating it if the seeder has not run.
     */
    public function agent(): static
    {
        return $this->withRole(UserRole::Agent);
    }

    /**
     * Give the user the requester role, creating it if the seeder has not run.
     */
    public function requester(): static
    {
        return $this->withRole(UserRole::Requester);
    }

    /**
     * Assign a role after creation. The role is created with the rank the enum
     * declares, so a test does not have to seed the whole hierarchy to get one
     * user with one role.
     */
    private function withRole(UserRole $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            $user->assignRole(Role::firstOrCreate(
                ['name' => $role->value],
                ['hierarchy_rank' => $role->hierarchyRank()],
            ));
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
