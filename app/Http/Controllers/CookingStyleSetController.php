<?php

namespace App\Http\Controllers;

use App\Models\CookingStyle;
use App\Models\CookingStyleSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Full CRUD for Cooking Style Sets (reusable bundles like "Seafood",
 * "Meat") and, on the same index page, lightweight inline CRUD for the
 * master CookingStyle list itself — individual styles are simple enough
 * (name + surcharge + sort order + active) not to need their own pages.
 */
class CookingStyleSetController extends Controller
{
    public function index(): Response
    {
        $sets = CookingStyleSet::withCount('menuItems')
            ->with('cookingStyles')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CookingStyleSet $set) => [
                'id' => $set->id,
                'name' => $set->name,
                'description' => $set->description,
                'sort_order' => $set->sort_order,
                'is_active' => $set->is_active,
                'menu_items_count' => $set->menu_items_count,
                'styles' => $set->cookingStyles->map(fn (CookingStyle $style) => ['id' => $style->id, 'name' => $style->name])->values(),
            ]);

        $styles = CookingStyle::withCount('menuItems')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CookingStyle $style) => [
                'id' => $style->id,
                'name' => $style->name,
                'surcharge' => (float) $style->surcharge,
                'sort_order' => $style->sort_order,
                'is_active' => $style->is_active,
            ]);

        return Inertia::render('Weigh/CookingStyles/Index', [
            'sets' => $sets,
            'styles' => $styles,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Weigh/CookingStyles/Create', [
            'availableStyles' => $this->activeStylesForFrontend(),
            'nextSortOrder' => CookingStyleSet::max('sort_order') + 1,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateSet($request);

        $set = CookingStyleSet::create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $set->cookingStyles()->sync($validated['cooking_style_ids'] ?? []);

        return redirect()->route('weigh.cooking-styles.index')
            ->with('status', __('":name" created.', ['name' => $set->name]));
    }

    public function edit(CookingStyleSet $cookingStyleSet): Response
    {
        return Inertia::render('Weigh/CookingStyles/Edit', [
            'set' => [
                'id' => $cookingStyleSet->id,
                'name' => $cookingStyleSet->name,
                'description' => $cookingStyleSet->description,
                'sort_order' => $cookingStyleSet->sort_order,
                'is_active' => $cookingStyleSet->is_active,
                'cooking_style_ids' => $cookingStyleSet->cookingStyles->pluck('id'),
            ],
            'availableStyles' => $this->activeStylesForFrontend(),
        ]);
    }

    public function update(Request $request, CookingStyleSet $cookingStyleSet): RedirectResponse
    {
        $validated = $this->validateSet($request);

        $cookingStyleSet->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        $cookingStyleSet->cookingStyles()->sync($validated['cooking_style_ids'] ?? []);

        return redirect()->route('weigh.cooking-styles.index')
            ->with('status', __('":name" updated.', ['name' => $cookingStyleSet->name]));
    }

    /**
     * Blocked (not archived-via-is_active) when items still use it — same
     * reasoning MenuCategoryController::destroy() uses: an item silently
     * losing its cooking styles on an unrelated cleanup is a surprise no
     * one asked for. Deactivate first if the intent is just to stop new
     * assignments.
     */
    public function destroy(CookingStyleSet $cookingStyleSet): RedirectResponse
    {
        if ($cookingStyleSet->menuItems()->exists()) {
            return redirect()->route('weigh.cooking-styles.index')
                ->with('error', __('Cannot delete ":name" — it is still assigned to one or more items. Reassign them first.', ['name' => $cookingStyleSet->name]));
        }

        $cookingStyleSet->delete();

        return redirect()->route('weigh.cooking-styles.index')
            ->with('status', __('":name" was deleted.', ['name' => $cookingStyleSet->name]));
    }

    public function toggleStatus(CookingStyleSet $cookingStyleSet): RedirectResponse
    {
        $cookingStyleSet->update(['is_active' => ! $cookingStyleSet->is_active]);

        return redirect()->back()->with('status', $cookingStyleSet->is_active
            ? __('":name" is now active.', ['name' => $cookingStyleSet->name])
            : __('":name" is now inactive.', ['name' => $cookingStyleSet->name]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateSet(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'cooking_style_ids' => ['nullable', 'array'],
            'cooking_style_ids.*' => [Rule::exists('cooking_styles', 'id')],
        ]);
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'set';
        $slug = $base;
        $suffix = 1;

        while (CookingStyleSet::where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$suffix;
        }

        return $slug;
    }

    /**
     * @return array<int, array{id: int, name: string, surcharge: float}>
     */
    protected function activeStylesForFrontend(): array
    {
        return CookingStyle::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CookingStyle $style) => ['id' => $style->id, 'name' => $style->name, 'surcharge' => (float) $style->surcharge])
            ->all();
    }

    /**
     * The master style list's inline "+ Add" row.
     */
    public function storeStyle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'surcharge' => ['nullable', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        CookingStyle::create([
            'name' => $validated['name'],
            'surcharge' => $validated['surcharge'] ?? 0,
            'sort_order' => $validated['sort_order'] ?? (CookingStyle::max('sort_order') + 1),
            'is_active' => true,
        ]);

        return redirect()->route('weigh.cooking-styles.index')->with('status', __('Cooking style added.'));
    }

    public function updateStyle(Request $request, CookingStyle $cookingStyle): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'surcharge' => ['nullable', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $cookingStyle->update([
            'name' => $validated['name'],
            'surcharge' => $validated['surcharge'] ?? 0,
            'sort_order' => $validated['sort_order'] ?? $cookingStyle->sort_order,
            'is_active' => $request->boolean('is_active', $cookingStyle->is_active),
        ]);

        return redirect()->route('weigh.cooking-styles.index')->with('status', __('Cooking style updated.'));
    }
}
