<?php

use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::get('/', fn (): Response => Inertia::render('auth/login'))->name('home');

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
