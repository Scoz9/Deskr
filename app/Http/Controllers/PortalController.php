<?php

namespace App\Http\Controllers;

use App\Http\Requests\Support\PortalLinkRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\PortalLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My requests": the portal a requester reaches without ever registering (§3).
 *
 * The link in the email is the credential, and opening it starts a session —
 * from there the portal is an ordinary set of pages scoped to whoever came
 * through the link, instead of a signature dragged through every address.
 */
class PortalController extends Controller
{
    /**
     * Nothing to authorize on the way in: whoever asks for a link is a stranger
     * until the signature proves otherwise, and from there the scope is the
     * person the session belongs to.
     */
    protected static bool $authorizesResources = false;

    /**
     * How many links one address may ask for in an hour (§5).
     */
    public const LINKS_PER_EMAIL_PER_HOUR = 3;

    /**
     * The page that asks for an address, and the one an expired link lands on.
     */
    public function request(): Response
    {
        return Inertia::render('portal/access', [
            'linkRequested' => (bool) session('linkRequested', false),
            'linkExpired' => (bool) session('linkExpired', false),
        ]);
    }

    /**
     * Send the link, if there is anybody to send it to.
     *
     * The answer is the same either way. A form that tells a known address from
     * an unknown one is a way to find out who is a customer of the helpdesk,
     * and this one is open to the internet.
     */
    public function link(PortalLinkRequest $request): RedirectResponse
    {
        $requester = User::query()
            ->where('email', $request->validated('email'))
            ->first();

        $requester?->notify(new PortalLink);

        return to_route('portal.request')->with('linkRequested', true);
    }

    /**
     * Open the portal for whoever the link was issued to.
     *
     * The signature is checked here and not by the `signed` middleware because
     * a link that has run out must not answer 403: whoever clicked it is
     * somebody the helpdesk wants to hear from, and what they need is the page
     * that hands out a fresh one (§5).
     */
    public function enter(Request $request, User $user): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return to_route('portal.request')->with('linkExpired', true);
        }

        auth()->login($user);
        $request->session()->regenerate();

        return to_route('portal.index');
    }

    /**
     * The requests of whoever is in the portal, and nobody else's.
     *
     * This is the one filter between two customers: there is no global scoping
     * underneath to catch what it misses (§3), and a colleague at the same
     * company is somebody else.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $requester = $request->user();

        if ($requester === null) {
            return to_route('portal.request');
        }

        return Inertia::render('portal/index', [
            'tickets' => $requester->tickets()
                ->get()
                ->map(fn (Ticket $ticket): array => [
                    'reference' => $ticket->reference,
                    'subject' => $ticket->subject,
                    'status' => $ticket->status->value,
                    'openedAt' => $ticket->created_at?->toIso8601String(),
                    'url' => route('support.ticket.show', $ticket),
                ])
                ->all(),
        ]);
    }

    /**
     * Close the portal. A screen in an office is not always somebody's own.
     */
    public function leave(Request $request): RedirectResponse
    {
        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('portal.request');
    }
}
