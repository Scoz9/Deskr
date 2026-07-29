<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Scrapkit\PermissionHierarchy\Concerns\HasRoleHierarchy;
use Scrapkit\UserSuspension\Concerns\Suspendable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $avatar_path
 * @property-read string|null $avatar
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $suspended_at
 * @property Carbon|null $suspended_until
 */
#[Appends(['avatar'])]
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'avatar_path'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoleHierarchy, HasRoles, Notifiable, PasskeyAuthenticatable, Suspendable, TwoFactorAuthenticatable;

    /**
     * The disk holding user avatars, and the directory they live in.
     */
    public const AVATAR_DISK = 'public';

    public const AVATAR_DIRECTORY = 'avatars';

    /**
     * Resolve the stored avatar into a public URL for the frontend.
     *
     * @return Attribute<string|null, never>
     */
    protected function avatar(): Attribute
    {
        return new Attribute(
            get: fn (): ?string => $this->avatar_path === null
                ? null
                : Storage::disk(self::AVATAR_DISK)->url($this->avatar_path),
        );
    }

    /**
     * Remove the avatar file backing this user, if there is one.
     */
    public function deleteAvatarFile(): void
    {
        if ($this->avatar_path !== null) {
            Storage::disk(self::AVATAR_DISK)->delete($this->avatar_path);
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
