<?php

namespace App\Http\Controllers;

use App\Http\Requests\Categories\CategoryDestroyRequest;
use App\Http\Requests\Categories\CategoryStoreRequest;
use App\Http\Requests\Categories\CategoryUpdateRequest;
use App\Models\Category;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administration of the taxonomy a request is filed under (roadmap step 41).
 * A category carries the team its tickets are routed to, which is what keeps
 * the routing deterministic and independent of the AI (§3).
 */
class CategoryController extends Controller
{
    /**
     * Show the categories management page.
     *
     * The teams travel with it because a category cannot exist without one:
     * the form has nothing to offer otherwise.
     */
    public function index(): Response
    {
        return Inertia::render('categories', [
            'categories' => Category::query()
                ->with('team:id,name')
                ->withCount('tickets')
                ->orderBy('name')
                ->get(['id', 'name', 'team_id']),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Create a new category, routed to a team.
     */
    public function store(CategoryStoreRequest $request): RedirectResponse
    {
        $category = Category::create($request->validated());

        flash()->created($category);

        return to_route('categories.index');
    }

    /**
     * Rename a category and/or re-route it to another team.
     *
     * The tickets already filed under it stay where they were sent: the
     * intake writes `team_id` on the ticket itself (§4), so re-routing
     * decides where the next ones go and rewrites nothing behind it.
     */
    public function update(CategoryUpdateRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        flash()->updated($category);

        return back();
    }

    /**
     * Delete a category, only if no ticket was ever filed under it.
     *
     * The check mirrors the `restrictOnDelete` on `tickets.category_id`:
     * without it the refusal would surface as a 500 instead of an error next
     * to the button that asked for it.
     */
    public function destroy(CategoryDestroyRequest $request, Category $category): RedirectResponse
    {
        if ($category->tickets()->exists()) {
            throw ValidationException::withMessages([
                'category' => __('Cannot delete a category that still has tickets.'),
            ]);
        }

        $category->delete();

        flash()->deleted($category);

        return to_route('categories.index');
    }
}
