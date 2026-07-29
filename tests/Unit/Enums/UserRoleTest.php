<?php

use App\Enums\UserRole;

test('the enum lists exactly the seeded spatie roles', function () {
    expect(array_map(fn (UserRole $role): string => $role->value, UserRole::cases()))
        ->toBe(['superAdmin', 'admin', 'agent', 'requester']);
});

test('every role carries the hierarchy rank the seeder and the factory share', function () {
    expect(array_map(fn (UserRole $role): int => $role->hierarchyRank(), UserRole::cases()))
        ->toBe([0, 1, 2, 3]);
});
