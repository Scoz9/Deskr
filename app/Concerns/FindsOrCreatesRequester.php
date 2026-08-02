<?php

namespace App\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The person behind an address, created if this is the first time they
 * write. The public intake of step 23 and the operator opening a ticket on
 * their behalf (step 39) both need this and neither is the one place to
 * decide it: writing to the helpdesk opens a ticket, it does not rename
 * somebody else, and the second time an address writes it lands on the
 * account the first one created.
 */
trait FindsOrCreatesRequester
{
    private function requesterFor(string $name, string $email): User
    {
        $requester = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Str::password()],
        );

        if ($requester->wasRecentlyCreated) {
            $requester->assignRole(UserRole::Requester->value);
        }

        return $requester;
    }
}
