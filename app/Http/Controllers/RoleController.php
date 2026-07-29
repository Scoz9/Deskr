<?php

namespace App\Http\Controllers;

use App\Http\Requests\Roles\RoleDestroyRequest;
use App\Http\Requests\Roles\RoleStoreRequest;
use App\Http\Requests\Roles\RoleUpdateRequest;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    /**
     * Show the roles management page.
     */
    public function index(): Response
    {
        return Inertia::render('roles', [
            'roles' => Role::query()
                ->where('name', '!=', 'superAdmin')
                ->with('permissions:id,name')
                ->orderBy('name')
                ->get(['id', 'name']),
            'permissions' => Permission::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Create a new role at the bottom of the hierarchy.
     */
    public function store(RoleStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $role = Role::create([
            'name' => $validated['name'],
            'hierarchy_rank' => ((int) Role::max('hierarchy_rank')) + 1,
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        flash()->created($role);

        return to_route('roles.index');
    }

    /**
     * Update a role's name and/or its permissions.
     */
    public function update(RoleUpdateRequest $request, Role $role): RedirectResponse
    {
        // Gate::before grants superAdmin users every ability, bypassing the
        // policy's protection of the superAdmin role — enforce it here too.
        abort_if($role->name === 'superAdmin', 404);

        $validated = $request->validated();

        if (array_key_exists('name', $validated)) {
            $role->update(['name' => $validated['name']]);
        }

        if (array_key_exists('permissions', $validated)) {
            $role->syncPermissions($validated['permissions']);
        }

        flash()->updated($role);

        return back();
    }

    /**
     * Delete a role, only if no user is assigned to it.
     */
    public function destroy(RoleDestroyRequest $request, Role $role): RedirectResponse
    {
        abort_if($role->name === 'superAdmin', 404);

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => __('Cannot delete a role that is assigned to users.'),
            ]);
        }

        $role->delete();

        flash()->deleted($role);

        return to_route('roles.index');
    }
}
