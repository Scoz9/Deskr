<?php

namespace App\Http\Controllers;

use App\Http\Requests\Teams\TeamDestroyRequest;
use App\Http\Requests\Teams\TeamStoreRequest;
use App\Http\Requests\Teams\TeamUpdateRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administration of the teams tickets are routed to (roadmap step 41).
 */
class TeamController extends Controller
{
    /**
     * Show the teams management page.
     *
     * The counts are what the page needs to say why a team cannot be deleted
     * before anybody tries: they are the same two things `destroy` refuses on.
     */
    public function index(): Response
    {
        return Inertia::render('teams', [
            'teams' => Team::query()
                ->withCount(['categories', 'members', 'tickets'])
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Create a new team.
     */
    public function store(TeamStoreRequest $request): RedirectResponse
    {
        $team = Team::create($request->validated());

        flash()->created($team);

        return to_route('teams.index');
    }

    /**
     * Rename a team.
     */
    public function update(TeamUpdateRequest $request, Team $team): RedirectResponse
    {
        $team->update($request->validated());

        flash()->updated($team);

        return back();
    }

    /**
     * Delete a team, only if nothing still points at it.
     *
     * Both checks mirror a `restrictOnDelete` the database already carries:
     * without them the refusal would surface as a 500 instead of an error
     * next to the button that asked for it. Membership is not among them —
     * the pivot cascades, and an agent losing a team they covered is not a
     * ticket losing where it was routed.
     */
    public function destroy(TeamDestroyRequest $request, Team $team): RedirectResponse
    {
        if ($team->categories()->exists()) {
            throw ValidationException::withMessages([
                'team' => __('Cannot delete a team that still has categories routed to it.'),
            ]);
        }

        if ($team->tickets()->exists()) {
            throw ValidationException::withMessages([
                'team' => __('Cannot delete a team that still has tickets.'),
            ]);
        }

        $team->delete();

        flash()->deleted($team);

        return to_route('teams.index');
    }
}
