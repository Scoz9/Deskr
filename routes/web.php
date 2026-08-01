<?php

use App\Http\Controllers\RoleController;
use App\Http\Controllers\SupportRequestController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::get('/', fn (): Response => Inertia::render('auth/login'))->name('home');

/*
 * The public intake. Its path is in Italian because it is read by whoever needs
 * help and not by whoever maintains the application, and it is throttled
 * because it is the one door that opens without an account: a form nobody has
 * to log into is a form a script can hammer.
 */
Route::middleware('throttle:intake')->group(function () {
    Route::get('assistenza', [SupportRequestController::class, 'create'])
        ->name('support.create');
});

Route::middleware(['auth', 'verified', 'not-suspended'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::resource('roles', RoleController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('users', UserController::class)->only(['index', 'store', 'update']);
    Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
    Route::delete('users/{user}/suspend', [UserController::class, 'unsuspend'])->name('users.unsuspend');

    // Shells for the notification kit: the pages talk to the package's own
    // JSON API, which authorizes every call on its own.
    Route::middleware('can:viewNotificationKit')->group(function () {
        Route::inertia('notifications', 'notifications')->name('notifications.index');
        Route::inertia('notifications/outbox', 'notifications/outbox')->name('notifications.outbox');
    });
});

require __DIR__.'/settings.php';
