<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::pluck('name')->each(fn (string $role) => $this->createUserForRole($role));
    }

    private function createUserForRole(string $role): void
    {
        $email = Str::kebab($role).'@example.test';

        $attributes = [
            ...User::factory()->definition(),
            'email' => $email,
            'name' => Str::headline($role),
        ];

        if (! app()->isLocal()) {
            $attributes['password'] = Hash::make(Str::random(32));
        }

        User::firstOrCreate(['email' => $email], $attributes)->assignRole($role);
    }
}
