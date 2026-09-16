<?php

namespace App\Http\Controllers;

use App\Enums\MenuItemAvailability;
use App\Enums\PricingType;
use App\Events\MenuItemAvailabilityChanged;
use App\Http\Requests\MenuItemRequest;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemAddOn;
use App\Models\MenuItemImage;
use App\Models\MenuItemVariant;
use App\Support\MenuDescriptionPurifier;
use App\Support\WeighedOrderSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MenuItemController extends Controller
{
    public function index(Request $request): Response
    {
        $showArchived = $request->boolean('archived');

        // Per-kilo items live on their own tab — their price moves daily via
        // the Daily Market Prices page, so mixing them into the regular
        // fixed-price catalog grid made that distinction easy to miss.
        $pricingTab = PricingType::tryFrom($request->string('pricing')->toString()) ?? PricingType::Fixed;

        $query = MenuItem::with(['menuCategory', 'images', 'variants', 'addOns', 'cookingStyles', 'cookingStyleSet.cookingStyles'])
            ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.menu_category_id')
            ->where('menu_items.pricing_type', $pricingTab)
            ->select('menu_items.*');

        if ($showArchived) {
            $query->onlyTrashed();
        }

        if ($search = trim((string) $request->string('q'))) {
            $query->where('menu_items.name', 'like', '%'.$search.'%');
        }

        if ($categoryId = $request->integer('category_id')) {
            $query->where('menu_items.menu_category_id', $categoryId);
        }

        $availability = $request->string('availability')->toString();
        if (MenuItemAvailability::tryFrom($availability)) {
            $query->where('menu_items.availability_status', $availability);
        }

        if ($request->boolean('featured')) {
            $query->where('menu_items.is_featured', true);
        }

        match ($request->string('sort')->toString()) {
            'name_asc' => $query->orderBy('menu_items.name'),
            'price_asc' => $query->orderBy('menu_items.price'),
            'price_desc' => $query->orderByDesc('menu_items.price'),
            'prep_asc' => $query->orderByRaw('menu_items.prep_time_minutes IS NULL, menu_items.prep_time_minutes'),
            'prep_desc' => $query->orderByDesc('menu_items.prep_time_minutes'),
            'newest' => $query->orderByDesc('menu_items.created_at'),
            // Group by category display order first (matches the category
            // labels shown on each card), then each item's own position
            // within that category.
            default => $query->orderBy('menu_categories.sort_order')->orderBy('menu_items.sort_order')->orderBy('menu_items.name'),
        };

        $items = $query->get()->map(fn (MenuItem $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'description_text' => $item->plainDescription(),
            'menu_category_id' => $item->menu_category_id,
            'category_name' => $item->menuCategory->name,
            'prep_time_minutes' => $item->prep_time_minutes,
            'is_featured' => $item->is_featured,
            'is_best_seller' => $item->is_best_seller,
            'availability_status' => $item->availability_status->value,
            'has_variants' => $item->hasVariants(),
            'variants_count' => $item->variants->count(),
            'has_add_ons' => $item->hasAddOns(),
            'add_ons_count' => $item->addOns->count(),
            'is_per_kilo' => $item->isPerKilo(),
            // Legacy per-kilo rows from before the "at least one cooking
            // style" rule — surfaced in the list rather than left to fail
            // in the weigh station mid-service.
            'needs_setup' => $item->needsWeighedSetup(),
            'price_range_label' => $item->priceRangeLabel(),
            'primary_image_url' => $item->primaryImageUrl(),
        ])->values();

        $categories = MenuCategory::withCount('menuItems')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('MenuItems/Index', [
            'items' => $items,
            'categories' => $categories,
            'hasCategories' => $categories->isNotEmpty(),
            'showArchived' => $showArchived,
            'archivedCount' => MenuItem::onlyTrashed()->where('pricing_type', $pricingTab)->count(),
            'pricingTab' => $pricingTab->value,
            'pricingCounts' => [
                'fixed' => MenuItem::where('pricing_type', PricingType::Fixed)->count(),
                'per_kilo' => MenuItem::where('pricing_type', PricingType::PerKilo)->count(),
            ],
            'filters' => $request->only(['q', 'category_id', 'availability', 'featured', 'sort']),
            'availabilityOptions' => $this->availabilityOptionsForFrontend(),
        ]);
    }

    /**
     * Plain enum cases serialize down to just their scalar value (PHP's
     * json_encode does this for backed enums), which would lose the
     * translated label() every availability dropdown/filter needs — so ship
     * {value,label,badgeClasses} instead of the raw cases.
     *
     * @return array<int, array{value: string, label: string, badgeClasses: string}>
     */
    protected function availabilityOptionsForFrontend(): array
    {
        return array_map(fn (MenuItemAvailability $option) => [
            'value' => $option->value,
            'label' => $option->label(),
            'badgeClasses' => $option->badgeClasses(),
        ], MenuItemAvailability::cases());
    }

    /**
     * Live "does this already exist?" check the item form fires as the
     * admin types a name, so a busy staff member re-keying an item they
     * simply didn't spot in a 150+ item catalog gets caught before a
     * duplicate is saved rather than discovered later during a cleanup.
     * Matches across ALL categories (a duplicate is just as likely to have
     * been filed under the wrong one) and includes archived items, since
     * "restore this" is usually the right fix rather than "create a new
     * one that looks the same."
     */
    public function checkDuplicate(Request $request): JsonResponse
    {
        $name = trim((string) $request->string('name'));

        if (mb_strlen($name) < 3) {
            return response()->json(['matches' => []]);
        }

        $excludeId = $request->integer('exclude') ?: null;
        $normalizedInput = $this->normalizeNameForMatching($name);

        $matches = MenuItem::withTrashed()
            ->with('menuCategory')
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->get()
            ->map(function (MenuItem $item) use ($normalizedInput) {
                $normalizedItem = $this->normalizeNameForMatching($item->name);
                similar_text($normalizedInput, $normalizedItem, $percent);

                return [
                    'item' => $item,
                    'score' => $percent,
                    'is_exact' => $normalizedItem === $normalizedInput,
                ];
            })
            ->filter(fn (array $row) => $row['is_exact'] || $row['score'] >= 72)
            ->sortByDesc(fn (array $row) => $row['is_exact'] ? 1000 : $row['score'])
            ->take(5)
            ->map(fn (array $row) => [
                'id' => $row['item']->id,
                'name' => $row['item']->name,
                'category_name' => $row['item']->menuCategory?->name,
                'availability_status' => $row['item']->availability_status->value,
                'is_archived' => $row['item']->trashed(),
                'is_exact' => $row['is_exact'],
                // An archived item's edit route 404s (soft-deleted rows
                // aren't route-bindable) — point those at the Archived tab
                // instead, where the admin can restore it, rather than a
                // dead link.
                'edit_url' => $row['item']->trashed()
                    ? route('menu-items.index', [
                        'archived' => 1,
                        'q' => $row['item']->name,
                        'pricing' => $row['item']->pricing_type->value,
                    ])
                    : route('menu-items.edit', $row['item']->id),
            ])
            ->values();

        return response()->json(['matches' => $matches]);
    }

    /**
     * Case/punctuation/spacing shouldn't matter for catching a duplicate —
     * "Bangus", "BANGUS", and "Bangus " are the same near-miss.
     */
    protected function normalizeNameForMatching(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9\s]/', '', mb_strtolower($name))));
    }

    public function create(): Response
    {
        $categories = MenuCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return Inertia::render('MenuItems/Create', [
            'categories' => $categories,
            'availabilityOptions' => $this->availabilityOptionsForFrontend(),
            'weighed' => WeighedOrderSettings::current()->toArray(),
            // Lets the form auto-fill Sort Order with "next in line" for
            // whichever category gets picked, instead of always showing 0
            // and leaving whoever's creating the item to guess the number.
            'nextSortOrders' => $categories->mapWithKeys(fn ($category) => [
                $category->id => (int) MenuItem::where('menu_category_id', $category->id)->max('sort_order') + 1,
            ]),
        ]);
    }

    public function store(MenuItemRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['images', 'variants', 'default_variant_index', 'add_ons']);
        $data['description'] = MenuDescriptionPurifier::clean($data['description'] ?? null);
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_best_seller'] = $request->boolean('is_best_seller');
        $data['availability_status'] = $request->input('availability_status', MenuItemAvailability::Available->value);
        // Base price is optional once variants exist (validated in the
        // FormRequest); the column itself stays NOT NULL, so an intentionally
        // blank price just becomes 0 — display already ignores it in favor
        // of the variant price range once variants are present.
        $data['price'] = $request->filled('price') ? $request->input('price') : 0;
        $data = $this->applyPerKiloFields($request, $data);

        $menuItem = MenuItem::create($data);

        $this->storeUploadedImages($request, $menuItem);
        $this->syncVariants($request, $menuItem);
        $this->syncAddOns($request, $menuItem);
        $this->clearCookingStylesIfNotPerKilo($menuItem);

        return redirect()->route('menu-items.index')
            ->with('status', __('Menu item created successfully.'));
    }

    public function edit(MenuItem $menuItem): Response
    {
        // Active categories plus the item's own current category, even if
        // it has since gone inactive — otherwise the dropdown wouldn't be
        // able to show/keep the item's existing assignment.
        $categories = MenuCategory::where('is_active', true)
            ->orWhere('id', $menuItem->menu_category_id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $menuItem->load(['images', 'variants', 'addOns']);

        return Inertia::render('MenuItems/Edit', [
            'item' => [
                'id' => $menuItem->id,
                'name' => $menuItem->name,
                'description' => $menuItem->description,
                'menu_category_id' => $menuItem->menu_category_id,
                'price' => $menuItem->price,
                'pricing_type' => $menuItem->pricing_type?->value ?? PricingType::Fixed->value,
                'price_per_kilo' => $menuItem->price_per_kilo,
                'min_weight_grams' => $menuItem->min_weight_grams,
                'counter_only' => $menuItem->counter_only,
                'sku' => $menuItem->sku,
                'prep_time_minutes' => $menuItem->prep_time_minutes,
                'availability_status' => $menuItem->availability_status->value,
                'sort_order' => $menuItem->sort_order,
                'is_featured' => $menuItem->is_featured,
                'is_best_seller' => $menuItem->is_best_seller,
                'images' => $menuItem->images->map(fn (MenuItemImage $image) => [
                    'id' => $image->id,
                    'url' => $image->url,
                    'is_primary' => $image->is_primary,
                ]),
                'variants' => $menuItem->variants->map(fn (MenuItemVariant $variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'description' => $variant->description,
                    'sku' => $variant->sku,
                    'price' => $variant->price,
                    'is_default' => $variant->is_default,
                    'image_url' => $variant->imageUrl(),
                ]),
                'add_ons' => $menuItem->addOns->map(fn (MenuItemAddOn $addOn) => [
                    'id' => $addOn->id,
                    'name' => $addOn->name,
                    'description' => $addOn->description,
                    'price' => $addOn->price,
                ]),
            ],
            'categories' => $categories,
            'availabilityOptions' => $this->availabilityOptionsForFrontend(),
            'weighed' => WeighedOrderSettings::current()->toArray(),
        ]);
    }

    public function update(MenuItemRequest $request, MenuItem $menuItem): RedirectResponse
    {
        $data = $request->safe()->except(['images', 'remove_images', 'primary_image_id', 'variants', 'default_variant_index', 'add_ons']);
        $data['description'] = MenuDescriptionPurifier::clean($data['description'] ?? null);
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_best_seller'] = $request->boolean('is_best_seller');
        $data['availability_status'] = $request->input('availability_status', $menuItem->availability_status->value);
        $data['price'] = $request->filled('price') ? $request->input('price') : 0;
        $data = $this->applyPerKiloFields($request, $data);

        $wasAvailability = $menuItem->availability_status;

        $menuItem->update($data);

        foreach ((array) $request->input('remove_images', []) as $imageId) {
            $image = $menuItem->images()->find($imageId);
            if ($image) {
                Storage::disk('public')->delete($image->path);
                $image->delete();
            }
        }

        $this->storeUploadedImages($request, $menuItem);
        $this->syncVariants($request, $menuItem);
        $this->syncAddOns($request, $menuItem);
        $this->clearCookingStylesIfNotPerKilo($menuItem);

        if ($primaryId = $request->integer('primary_image_id')) {
            $menuItem->images()->update(['is_primary' => false]);
            $menuItem->images()->where('id', $primaryId)->update(['is_primary' => true]);
        } elseif (! $menuItem->images()->where('is_primary', true)->exists()) {
            // Deletions/replacements can leave no image flagged primary —
            // fall back to the first one by display order so the grid/
            // customer views always have something to show.
            $menuItem->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
        }

        if ($menuItem->availability_status !== $wasAvailability) {
            broadcast(new MenuItemAvailabilityChanged($menuItem));
        }

        return redirect()->route('menu-items.index')
            ->with('status', __('Menu item updated successfully.'));
    }

    /**
     * Quick one-click "86 it" toggle (Available <-> Out of Stock) and the
     * full Seasonal/Hidden picker both post here. Called via fetch() from
     * the grid, not a form submit, so the page's search/filter query string
     * doesn't get lost — falls back to a classic redirect for non-JS callers.
     */
    public function setAvailability(Request $request, MenuItem $menuItem): RedirectResponse|JsonResponse
    {
        $status = MenuItemAvailability::tryFrom((string) $request->string('status'));

        abort_if($status === null, 422, __('Invalid availability status.'));

        $menuItem->update(['availability_status' => $status]);

        broadcast(new MenuItemAvailabilityChanged($menuItem));

        if ($request->wantsJson()) {
            return response()->json(['availability_status' => $status->value]);
        }

        return redirect()->back()->with('status', __('":name" is now :status.', [
            'name' => $menuItem->name,
            'status' => $status->label(),
        ]));
    }

    /**
     * Archives (soft-deletes) rather than permanently removing — historical
     * order_items keep their frozen item_name/unit_price either way, but a
     * reversible archive avoids irreversibly losing setup work (images,
     * description, etc.) from an accidental click.
     */
    public function destroy(MenuItem $menuItem): RedirectResponse
    {
        $menuItem->delete();

        return redirect()->back()
            ->with('status', __('":name" was archived.', ['name' => $menuItem->name]));
    }

    public function restore(MenuItem $menuItem): RedirectResponse
    {
        $menuItem->restore();

        return redirect()->route('menu-items.index', ['archived' => 1])
            ->with('status', __('":name" was restored.', ['name' => $menuItem->name]));
    }

    /**
     * Diffs the submitted variant rows against what the item already has:
     * rows with a matching `id` are updated, rows with no `id` are created,
     * and any existing variant whose `id` wasn't resubmitted at all (the
     * user removed its row client-side) gets archived — same "the absence
     * of a row means it's gone" approach as the images uploader, just
     * without a file to clean up.
     */
    protected function syncVariants(Request $request, MenuItem $menuItem): void
    {
        $rows = $request->input('variants', []);
        $defaultIndex = $request->input('default_variant_index');
        $keptIds = [];

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $attributes = [
                'menu_item_id' => $menuItem->id,
                'name' => $name,
                'description' => $row['description'] ?: null,
                'sku' => $row['sku'] ?: null,
                'price' => $row['price'] ?? 0,
                'sort_order' => $index,
                'is_default' => $defaultIndex !== null && (int) $defaultIndex === (int) $index,
            ];

            $variant = ! empty($row['id']) ? $menuItem->variants()->find($row['id']) : null;

            // Most variants (Solo/Medium/Large) look like the base dish and
            // don't need their own photo — only set/replace/remove one when
            // this row actually says to, matching the images uploader's
            // upload-or-remove-or-leave-alone behavior.
            if ($request->boolean("variants.{$index}.remove_image") && $variant?->image_path) {
                Storage::disk('public')->delete($variant->image_path);
                $attributes['image_path'] = null;
            } elseif ($request->hasFile("variants.{$index}.image")) {
                if ($variant?->image_path) {
                    Storage::disk('public')->delete($variant->image_path);
                }
                $attributes['image_path'] = $request->file("variants.{$index}.image")->store('menu-items', 'public');
            }

            if ($variant) {
                $variant->update($attributes);
            } else {
                $variant = $menuItem->variants()->create($attributes);
            }

            $keptIds[] = $variant->id;
        }

        foreach ($menuItem->variants()->whereNotIn('id', $keptIds)->get() as $removedVariant) {
            if ($removedVariant->image_path) {
                Storage::disk('public')->delete($removedVariant->image_path);
            }
        }
        $menuItem->variants()->whereNotIn('id', $keptIds)->delete();

        if ($menuItem->variants()->exists() && ! $menuItem->variants()->where('is_default', true)->exists()) {
            $menuItem->variants()->orderBy('sort_order')->first()?->update(['is_default' => true]);
        }
    }

    /**
     * Same diff-by-id approach as syncVariants() above, minus the image/
     * SKU/default handling variants need — an add-on is just a name, an
     * optional description (e.g. "150g per order"), and a price, and
     * unlike a variant there's no "pick exactly one" rule to enforce here.
     */
    protected function syncAddOns(Request $request, MenuItem $menuItem): void
    {
        $keptIds = [];

        foreach ($request->input('add_ons', []) as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $attributes = [
                'menu_item_id' => $menuItem->id,
                'name' => $name,
                'description' => $row['description'] ?: null,
                // No fixed price (e.g. a "customer's choice" seafood
                // add-on priced at the counter) is a real, intentional
                // case, not a mistake — but the column itself stays
                // NOT NULL, so treat a blank submission the same as an
                // explicit 0 rather than ?? which only catches null, not
                // the empty string an untouched number input actually
                // submits.
                'price' => $row['price'] !== '' && $row['price'] !== null ? $row['price'] : 0,
                'sort_order' => $index,
            ];

            $addOn = ! empty($row['id']) ? $menuItem->addOns()->find($row['id']) : null;
            $addOn = $addOn ? tap($addOn)->update($attributes) : $menuItem->addOns()->create($attributes);

            $keptIds[] = $addOn->id;
        }

        $menuItem->addOns()->whereNotIn('id', $keptIds)->delete();
    }

    /**
     * Normalize the per-kilo pricing inputs. A fixed-price item always has
     * its weight-only fields reset to the column defaults, so stale config
     * can't linger after switching an item back from per-kilo — and a
     * per-kilo item can't keep a leftover fixed `price`, which would show a
     * second, wrong number next to its ₱/kg rate.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function applyPerKiloFields(Request $request, array $data): array
    {
        $isPerKilo = $request->input('pricing_type') === PricingType::PerKilo->value;
        $data['pricing_type'] = $isPerKilo ? PricingType::PerKilo->value : PricingType::Fixed->value;

        if (! $isPerKilo) {
            // Toggling back to fixed clears the weighed config outright, so
            // an item can't carry a stale rate that nothing displays.
            return $data + [
                'price_per_kilo' => null,
                'min_weight_grams' => 250,
                'cooking_style_set_id' => null,
                'counter_only' => false,
            ];
        }

        $data['price'] = 0;
        $data['price_per_kilo'] = $request->input('price_per_kilo');
        $data['min_weight_grams'] = (int) $request->input('min_weight_grams');
        // Not read from the request: a weighed item is always handed over in
        // person, and the model enforces this again on save so a direct API
        // post can't turn it off either.
        $data['counter_only'] = true;

        return $data;
    }

    /**
     * A fixed item carries no cooking-style override — clear any leftover
     * rows from a previous stint as a per-kilo item. Assignment of a
     * cooking style SET, and any per-item override, is edited from the
     * Weigh & Order "Weighted Items" screen, not this form.
     */
    protected function clearCookingStylesIfNotPerKilo(MenuItem $menuItem): void
    {
        if (! $menuItem->isPerKilo()) {
            $menuItem->cookingStyles()->detach();
        }
    }

    protected function storeUploadedImages(Request $request, MenuItem $menuItem): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        $nextSortOrder = (int) $menuItem->images()->max('sort_order') + 1;
        $hasPrimary = $menuItem->images()->where('is_primary', true)->exists();

        foreach ($request->file('images') as $index => $file) {
            $menuItem->images()->create([
                'path' => $file->store('menu-items', 'public'),
                'sort_order' => $nextSortOrder + $index,
                'is_primary' => ! $hasPrimary && $index === 0,
            ]);
        }
    }
}
