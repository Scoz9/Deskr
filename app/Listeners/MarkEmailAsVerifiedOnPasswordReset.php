<?php

namespace App\Listeners;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class MarkEmailAsVerifiedOnPasswordReset
{
    /**
     * Completing a reset proves ownership of the mailbox the link was sent
     * to, so an unverified email can be marked as verified as well.
     */
    public function handle(PasswordReset $event): void
    {
        if ($event->user instanceof MustVerifyEmail && ! $event->user->hasVerifiedEmail()) {
            $event->user->markEmailAsVerified();
        }
    }
}
