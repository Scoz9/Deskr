<?php

use App\Enums\UserRole;

test('the enum lists exactly the seeded spatie roles', function () {
    expect(array_map(fn (UserRole $role): string => $role->value, UserRole::cases()))
        ->toBe(['superAdmin', 'admin', 'agent', 'requester']);
});
