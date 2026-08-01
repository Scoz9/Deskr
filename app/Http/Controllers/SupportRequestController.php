<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public intake: the only page of the application that answers somebody
 * without an account, because a requester never registers (§3).
 *
 * Fields only for now. Turning what is typed here into a ticket through
 * `CreateTicket` is step 23.
 */
class SupportRequestController extends Controller
{
    /**
     * No resource authorization here, and it is not an omission: the intake
     * answers a guest by design, so there is neither a policy to consult nor a
     * user to consult it about. What defends this door is the rate limit on the
     * route and the honeypot in the form.
     */
    protected static bool $authorizesResources = false;

    /**
     * Show the form, with the categories it has to offer.
     *
     * The category is what routes the ticket to a team, so it is the one thing
     * the form cannot make up on its own. Nothing else of the category travels
     * to a public page: the team behind it is how the helpdesk is organised
     * inside, and whoever is asking for help has no business reading it.
     */
    public function create(): Response
    {
        return Inertia::render('support/create', [
            'categories' => Category::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
