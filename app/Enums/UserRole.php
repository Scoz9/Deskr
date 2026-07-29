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
}
