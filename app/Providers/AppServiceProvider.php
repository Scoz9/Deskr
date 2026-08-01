<?php

namespace App\Providers;

use App\Http\Controllers\SupportRequestController;
use App\Models\Role;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::before(function ($user, $ability) {
            return $user->hasRole('superAdmin') ? true : null;
        });

        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        $this->configureNotificationKitGates();
        $this->configureRateLimiting();
    }

    /**
     * Rate limit the public intake (§5).
     *
     * Per IP, which is all there is to key on while the form only renders: the
     * limit per email address and the cap on open tickets per address arrive
     * with the submission of step 23, where an address exists to count.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('intake', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));

        // Keyed on the address and not on the network: an address is the one
        // thing that stays the same when whoever is sending moves connection.
        RateLimiter::for('intake-email', fn (Request $request): Limit => Limit::perHour(
            SupportRequestController::SUBMISSIONS_PER_EMAIL_PER_HOUR,
        )->by(Str::transliterate(Str::lower((string) $request->input('email')))));
    }

    /**
     * Map the notification kit abilities onto this application's permissions.
     *
     * The package ships no defaults on purpose: without these gates its API
     * denies everyone.
     */
    protected function configureNotificationKitGates(): void
    {
        $gates = [
            'viewNotificationKit' => 'notification:viewAny',
            'notification-kit.view' => 'notification:viewAny',
            'notification-kit.update-content' => 'notification:update',
            'notification-kit.archive' => 'notification:archive',
            'notification-kit.approve' => 'notification:approve',
        ];

        foreach ($gates as $gate => $permission) {
            Gate::define($gate, fn (User $user): bool => $user->can($permission));
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
