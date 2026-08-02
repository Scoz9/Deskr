<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PostmarkInboundController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SupportRequestController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketMessageController;
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

    // The submission is limited twice: by IP like the page, and by the address
    // it carries, which is the only thing that stays the same when whoever is
    // sending changes network (§5).
    Route::post('assistenza', [SupportRequestController::class, 'store'])
        ->middleware('throttle:intake-email')
        ->name('support.store');
});

/*
 * The ticket as whoever asked sees it. Two ways in and no third: the signature
 * on the link the confirmation email carries, or the portal session of whoever
 * opened the request. The check is in the controller because the two are read
 * together — a requester never logs in with a password (§3), so neither alone
 * is "the" credential.
 */
Route::get('assistenza/ticket/{ticket}', [SupportTicketController::class, 'show'])
    ->name('support.ticket.show');

/*
 * The reply from the portal. There is only one way in: the portal session,
 * because a POST needs identity and CSRF, not a signature in the query
 * string (§3) — the signed link of the confirmation email stops at `show`.
 */
Route::post('assistenza/ticket/{ticket}/rispondi', [SupportTicketController::class, 'reply'])
    ->name('support.ticket.reply');

/*
 * "My requests". The link asked for here is the credential, so the request for
 * it is throttled twice — by IP like the intake, and by the address it names.
 */
Route::middleware('throttle:intake')->group(function () {
    Route::get('portale', [PortalController::class, 'request'])->name('portal.request');

    Route::post('portale', [PortalController::class, 'link'])
        ->middleware('throttle:portal-email')
        ->name('portal.link');

    Route::get('portale/entra/{user}', [PortalController::class, 'enter'])->name('portal.enter');

    Route::get('portale/richieste', [PortalController::class, 'index'])->name('portal.index');

    Route::post('portale/esci', [PortalController::class, 'leave'])->name('portal.leave');
});

/*
 * The bytes of an attachment, and the only way to them: the disk is private and
 * serves nothing on its own, so the signature on the link is what says the
 * application handed it out. It is outside the intake group because downloading
 * a file the helpdesk itself linked is not asking for help.
 */
Route::get('allegati/{attachment}', [AttachmentController::class, 'show'])
    ->middleware('signed')
    ->name('attachments.show');

/*
 * The email channel. The caller is Postmark, not a browser, so there is no
 * session and no CSRF token to check — the credential is the HTTP Basic Auth
 * the request itself verifies (§6: an endpoint the mail provider can reach).
 */
Route::post('webhooks/postmark/inbound', [PostmarkInboundController::class, 'store'])
    ->name('webhooks.postmark.inbound');

Route::middleware(['auth', 'verified', 'not-suspended'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::resource('tickets', TicketController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('tickets/{ticket}/assign-to-me', [TicketController::class, 'assignToMe'])->name('tickets.assign-to-me');
    Route::patch('tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.update-status');
    Route::patch('tickets/{ticket}/priority', [TicketController::class, 'updatePriority'])->name('tickets.update-priority');
    Route::post('tickets/{ticket}/messages', [TicketMessageController::class, 'store'])->name('tickets.messages.store');

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
