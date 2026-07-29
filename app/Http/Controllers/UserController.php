<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\UserStoreRequest;
use App\Http\Requests\Users\UserSuspendRequest;
use App\Http\Requests\Users\UserUnsuspendRequest;
use App\Http\Requests\Users\UserUpdateRequest;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Register the custom abilities on top of the resource ability map.
     *
     * @return array<string, string>
     */
    protected static function resourceAbilityMap(): array
    {
        return [
            ...parent::resourceAbilityMap(),
            'suspend' => 'suspend',
            'unsuspend' => 'unsuspend',
        ];
    }

    /**
     * Show the users management page.
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();

        return Inertia::render('users', [
            'users' => User::query()
                ->with('roles:id,name,hierarchy_rank')
                ->whereDoesntHave('roles', fn (Builder $query) => $query->where('name', 'superAdmin'))
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->only(['id', 'name']),
                    'is_suspended' => $user->isSuspended(),
                    'suspended_at' => $user->suspended_at,
                    'suspended_until' => $user->suspended_until,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                    'can_update' => $actor->can('update', $user),
                    'can_suspend' => $actor->can('suspend', $user),
                ]),
            'roles' => $actor->hierarchyRank() === null
                ? collect()
                : Role::query()
                    ->where('name', '!=', 'superAdmin')
                    ->where('hierarchy_rank', '>', $actor->hierarchyRank())
                    ->orderBy('hierarchy_rank')
                    ->get(['id', 'name', 'hierarchy_rank']),
        ]);
    }

    /**
     * Create a new user and invite them to choose their own password.
     *
     * The invitation carries a password reset link; completing the reset
     * also marks the email as verified, so no verification email is sent.
     */
    public function store(UserStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Str::password(),
        ]);

        $user->assignRole($validated['role']);

        $user->notify(new UserInvitation(Password::createToken($user)));

        flash()->created($user);

        return to_route('users.index');
    }

    /**
     * Update a user's profile, password and/or role.
     */
    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        // Gate::before grants superAdmin users every ability, bypassing the
        // policy's hierarchy check — keep superAdmin users untouchable here too.
        abort_if($user->hasRole('superAdmin'), 404);

        $validated = $request->validated();

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            ...filled($validated['password'] ?? null) ? ['password' => $validated['password']] : [],
        ]);

        if (array_key_exists('role', $validated)) {
            $user->syncRoles(array_filter([$validated['role']]));
        }

        flash()->updated($user);

        return back();
    }

    /**
     * Suspend a user, permanently or until the given moment.
     */
    public function suspend(UserSuspendRequest $request, User $user): RedirectResponse
    {
        abort_if($user->hasRole('superAdmin'), 404);
        abort_if($user->is($request->user()), 403);

        $validated = $request->validated();

        filled($validated['suspended_until'] ?? null)
            ? $user->suspendUntil(Date::parse($validated['suspended_until']))
            : $user->suspend();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User suspended.')]);

        return back();
    }

    /**
     * Lift a user's suspension.
     */
    public function unsuspend(UserUnsuspendRequest $request, User $user): RedirectResponse
    {
        abort_if($user->hasRole('superAdmin'), 404);

        $user->unsuspend();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User unsuspended.')]);

        return back();
    }
}
