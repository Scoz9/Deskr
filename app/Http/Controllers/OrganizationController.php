<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organizations\OrganizationDestroyRequest;
use App\Http\Requests\Organizations\OrganizationStoreRequest;
use App\Http\Requests\Organizations\OrganizationUpdateRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administration of the requester's company (roadmap step 40) — a domain
 * entity for grouping and reporting, not a tenant (§4: nothing is scoped by
 * it), so its CRUD is a flat list and nothing more.
 */
class OrganizationController extends Controller
{
    /**
     * Show the organizations management page.
     */
    public function index(): Response
    {
        return Inertia::render('organizations', [
            'organizations' => Organization::query()
                ->withCount('users')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Create a new organization.
     */
    public function store(OrganizationStoreRequest $request): RedirectResponse
    {
        $organization = Organization::create($request->validated());

        flash()->created($organization);

        return to_route('organizations.index');
    }

    /**
     * Rename an organization.
     */
    public function update(OrganizationUpdateRequest $request, Organization $organization): RedirectResponse
    {
        $organization->update($request->validated());

        flash()->updated($organization);

        return back();
    }

    /**
     * Delete an organization, only if no requester still belongs to it.
     */
    public function destroy(OrganizationDestroyRequest $request, Organization $organization): RedirectResponse
    {
        if ($organization->users()->exists()) {
            throw ValidationException::withMessages([
                'organization' => __('Cannot delete an organization that still has users.'),
            ]);
        }

        $organization->delete();

        flash()->deleted($organization);

        return to_route('organizations.index');
    }
}
