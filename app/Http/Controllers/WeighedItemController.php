<?php

namespace App\Http\Controllers;

use App\Enums\PricingType;
use App\Models\CookingStyle;
use App\Models\CookingStyleSet;
use App\Models\MenuItem;
use App\Services\WeighedItemReadiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A read-mostly operations view over every per-kilo menu item — creation
 * itself stays the one Menu Management item form; this is where cooking
 * style assignment happens instead (moved off that form entirely).
 */
class WeighedItemController extends Controller
{
    public function index(): Response
    {
        $items = MenuItem::where('pricing_type', PricingType::PerKilo)
            ->with(['menuCategory', 'cookingStyles', 'cookingStyleSet.cookingStyles'])
            ->orderBy('menu_category_id')
            ->orderBy('name')
            ->get()
            ->map(function (MenuItem $item) {
                $reasons = WeighedItemReadiness::reasons($item);

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'category_name' => $item->menuCategory->name,
                    'price_per_kilo' => (float) $item->effectivePricePerKilo(),
                    'min_weight_grams' => (int) $item->min_weight_grams,
                    'counter_only' => $item->counter_only,
                    'cooking_style_set_id' => $item->cooking_style_set_id,
                    'weighed_sort_order' => $item->weighed_sort_order,
                    'override_style_ids' => $item->cookingStyles->pluck('id'),
                    'resolved_styles' => $item->resolvedCookingStyles()->map(fn ($style) => [
                        'id' => $style->id,
                        'name' => $style->name,
                    ])->values(),
                    'ready' => $reasons === [],
                    'reasons' => $reasons,
                    'edit_url' => route('menu-items.edit', $item),
                ];
            })
            ->values();

        return Inertia::render('Weigh/Items/Index', [
            'items' => $items,
            'cookingStyleSets' => $this->activeSetsForFrontend(),
            'cookingStyles' => $this->activeStylesForFrontend(),
        ]);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    protected function activeSetsForFrontend(): array
    {
        return CookingStyleSet::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CookingStyleSet $set) => ['id' => $set->id, 'name' => $set->name])
            ->all();
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
            ->map(fn ($style) => ['id' => $style->id, 'name' => $style->name, 'surcharge' => (float) $style->surcharge])
            ->all();
    }

    /**
     * Assigns a cooking style set and/or a per-item override to one
     * per-kilo item. `cooking_style_ids` is only touched when the request
     * actually sends the key — omitting it (e.g. only changing the set)
     * leaves an existing override untouched rather than silently clearing
     * it.
     */
    public function update(Request $request, MenuItem $menuItem): RedirectResponse
    {
        abort_unless($menuItem->isPerKilo(), 404);

        $validated = $request->validate([
            'cooking_style_set_id' => ['nullable', Rule::exists('cooking_style_sets', 'id')],
            'cooking_style_ids' => ['nullable', 'array'],
            'cooking_style_ids.*' => [Rule::exists('cooking_styles', 'id')],
            'weighed_sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $menuItem->update([
            'cooking_style_set_id' => $validated['cooking_style_set_id'] ?? null,
            'weighed_sort_order' => $request->has('weighed_sort_order') ? $validated['weighed_sort_order'] : $menuItem->weighed_sort_order,
        ]);

        if ($request->has('cooking_style_ids')) {
            $menuItem->cookingStyles()->sync($validated['cooking_style_ids'] ?? []);
        }

        return redirect()->route('weigh.items.index')
            ->with('status', __('":name" updated.', ['name' => $menuItem->name]));
    }
}
