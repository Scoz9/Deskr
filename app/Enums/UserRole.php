<?php

namespace App\Enums;

/**
 * Type-safe list of the seeded spatie roles, for factories, seeders and
 * policies. It does not implement roles: `spatie/laravel-permission` with the
 * hierarchy of `scrapkit/laravel-permission-hierarchy` is the only authority
 * on authorization, and `users` carries no role column.
 */
enum UserRole: string
{
    case SuperAdmin = 'superAdmin';
    case Admin = 'admin';
    case Agent = 'agent';
    case Requester = 'requester';

    /**
     * Rank in the hierarchy of `scrapkit/laravel-permission-hierarchy`: the
     * lower it is, the more the role can manage. Seeder and factory read it
     * from here so the two never disagree.
     */
    public function hierarchyRank(): int
    {
        return match ($this) {
            self::SuperAdmin => 0,
            self::Admin => 1,
            self::Agent => 2,
            self::Requester => 3,
        };
    }
}
