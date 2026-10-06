<?php

namespace App\Http\Controllers\Massage;

use App\Http\Controllers\Controller;
use App\Models\MassageService;
use App\Models\MassageServiceImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Massage Services — the Massage department's own list, the way Menu
 * Management is the restaurant's. Everyone in Massage can see it and
 * switch a service on or off; adding, editing and archiving is for an
 * Admin or Superadmin, as with the menu. The form is New Menu Item's
 * layout (Massage/ServiceForm.jsx), photos and all.
 */
class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $archived = $request->boolean('archived');

        return view('massage.services.index', [
            'services' => ($archived ? MassageService::onlyTrashed() : MassageService::query())->with(['images', 'variants', 'addOns'])->ordered()->get(),
            'archived' => $archived,
            'archivedCount' => MassageService::onlyTrashed()->count(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Massage/ServiceForm', [
            'service' => null,
            'nextSortOrder' => (int) MassageService::withTrashed()->max('sort_order') + 1,
        ]);
    }

    public function store(Request $request): SymfonyResponse
    {
        $service = DB::transaction(function () use ($request) {
            $service = MassageService::create($this->validated($request));
            $this->saveVariantsAndAddOns($request, $service);

            return $service;
        });
        $this->saveImages($request, $service);

        return $this->backToList(__('":name" was added.', ['name' => $service->name]));
    }

    public function edit(MassageService $service): Response
    {
        $service->load(['images', 'variants', 'addOns']);

        return Inertia::render('Massage/ServiceForm', [
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'price' => $service->price,
                'duration_minutes' => $service->duration_minutes,
                'is_available' => $service->is_available,
                'sort_order' => $service->sort_order,
                'images' => $service->images->map(fn (MassageServiceImage $image) => [
                    'id' => $image->id,
                    'url' => $image->url,
                    'is_primary' => $image->is_primary,
                ])->values(),
                'variants' => $service->variants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'description' => $variant->description ?? '',
                    'price' => $variant->price,
                    'duration_minutes' => $variant->duration_minutes,
                    'is_default' => $variant->is_default,
                ])->values(),
                'add_ons' => $service->addOns->map(fn ($addOn) => [
                    'id' => $addOn->id,
                    'name' => $addOn->name,
                    'description' => $addOn->description ?? '',
                    'price' => $addOn->price,
                ])->values(),
            ],
            'nextSortOrder' => $service->sort_order,
        ]);
    }

    public function update(Request $request, MassageService $service): SymfonyResponse
    {
        DB::transaction(function () use ($request, $service) {
            $service->update($this->validated($request, $service));
            $this->saveVariantsAndAddOns($request, $service);
        });
        $this->saveImages($request, $service);

        return $this->backToList(__('":name" was updated.', ['name' => $service->name]));
    }

    /**
     * The form is a React page but the list is a Blade one, so a save from
     * the form goes back with a full page load (Inertia::location) — an
     * Inertia redirect would try to show the Blade page inside a modal.
     */
    protected function backToList(string $message): SymfonyResponse
    {
        session()->flash('status', $message);

        return Inertia::location(route('massage.services.index'));
    }

    public function setAvailability(Request $request, MassageService $service): RedirectResponse
    {
        $service->update(['is_available' => $request->boolean('is_available')]);

        return back()->with('status', $service->is_available
            ? __('":name" is available again.', ['name' => $service->name])
            : __('":name" is now unavailable.', ['name' => $service->name]));
    }

    public function destroy(MassageService $service): RedirectResponse
    {
        $service->delete();

        return redirect()->route('massage.services.index')
            ->with('status', __('":name" was archived.', ['name' => $service->name]));
    }

    public function restore(int $service): RedirectResponse
    {
        $service = MassageService::onlyTrashed()->findOrFail($service);
        $service->restore();

        return redirect()->route('massage.services.index')
            ->with('status', __('":name" is back on the list.', ['name' => $service->name]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?MassageService $service = null): array
    {
        // As on a menu item: the price can be left blank when a variant has
        // one — then each variant's own price is what gets charged.
        $hasPricedVariant = collect($request->input('variants', []))
            ->contains(fn ($variant) => trim((string) ($variant['name'] ?? '')) !== '' && ($variant['price'] ?? '') !== '' && ($variant['price'] ?? null) !== null);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('massage_services', 'name')->ignore($service?->id)->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:10000'],
            'price' => [$hasPricedVariant ? 'nullable' : 'required', 'numeric', 'min:0', 'max:999999'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'images' => ['nullable', 'array', 'max:6'],
            'images.*' => ['image', 'max:5120'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
            'primary_image_id' => ['nullable', 'integer'],
            'variants' => ['nullable', 'array', 'max:20'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required', 'string', 'max:100'],
            'variants.*.description' => ['nullable', 'string', 'max:255'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'variants.*.duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'default_variant_index' => ['nullable', 'integer', 'min:0'],
            'add_ons' => ['nullable', 'array', 'max:30'],
            'add_ons.*.id' => ['nullable', 'integer'],
            'add_ons.*.name' => ['required', 'string', 'max:150'],
            'add_ons.*.description' => ['nullable', 'string', 'max:255'],
            'add_ons.*.price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ], [
            'price.required' => __('Enter a price, or add a variant with a price below.'),
            'variants.*.name.required' => __('Every variant needs a name.'),
            'add_ons.*.name.required' => __('Every add-on needs a name.'),
        ]);

        $price = $validated['price'] ?? null;
        if ($price === null || $price === '') {
            // Kept for sorting and old screens: the lowest variant price.
            $price = collect($validated['variants'] ?? [])->pluck('price')->filter(fn ($p) => $p !== null && $p !== '')->min() ?? 0;
        }

        return collect($validated)->only(['name', 'description', 'duration_minutes'])->all() + [
            'price' => $price,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_available' => $request->boolean('is_available', true),
        ];
    }

    /**
     * Same as a menu item's variants and add-ons: a row with an id is
     * updated, a row without one is added, and a row no longer sent is
     * deleted. Order lines keep their own copies, so this never changes a
     * past order.
     */
    protected function saveVariantsAndAddOns(Request $request, MassageService $service): void
    {
        $variants = array_values((array) $request->input('variants', []));
        $defaultIndex = $request->input('default_variant_index');
        $keptVariantIds = [];

        // The default has to be one that can be ordered.
        $orderable = fn ($row) => isset($row['price']) && $row['price'] !== '' && $row['price'] !== null;
        if ($defaultIndex === null || ! isset($variants[$defaultIndex]) || ! $orderable($variants[$defaultIndex])) {
            $defaultIndex = collect($variants)->search($orderable);
            $defaultIndex = $defaultIndex === false ? null : $defaultIndex;
        }

        foreach ($variants as $index => $row) {
            $attributes = [
                'name' => trim($row['name']),
                'description' => ($row['description'] ?? null) ?: null,
                'price' => $orderable($row) ? $row['price'] : null,
                'duration_minutes' => ($row['duration_minutes'] ?? null) ?: null,
                'is_default' => $defaultIndex !== null && (int) $defaultIndex === $index,
                'sort_order' => $index,
            ];

            $existing = ! empty($row['id']) ? $service->variants()->find($row['id']) : null;
            $variant = $existing ? tap($existing)->update($attributes) : $service->variants()->create($attributes);
            $keptVariantIds[] = $variant->id;
        }
        $service->variants()->whereNotIn('id', $keptVariantIds)->delete();

        $keptAddOnIds = [];
        foreach (array_values((array) $request->input('add_ons', [])) as $index => $row) {
            $attributes = [
                'name' => trim($row['name']),
                'description' => ($row['description'] ?? null) ?: null,
                'price' => ($row['price'] ?? '') === '' || ($row['price'] ?? null) === null ? 0 : $row['price'],
                'sort_order' => $index,
            ];

            $existing = ! empty($row['id']) ? $service->addOns()->find($row['id']) : null;
            $addOn = $existing ? tap($existing)->update($attributes) : $service->addOns()->create($attributes);
            $keptAddOnIds[] = $addOn->id;
        }
        $service->addOns()->whereNotIn('id', $keptAddOnIds)->delete();
    }

    /**
     * Same rules as a menu item's photos: removed ones are deleted from
     * storage, new ones are added after the rest, and there is always one
     * primary while any photo is left.
     */
    protected function saveImages(Request $request, MassageService $service): void
    {
        foreach ((array) $request->input('remove_images', []) as $imageId) {
            $image = $service->images()->find($imageId);
            if ($image) {
                Storage::disk('public')->delete($image->path);
                $image->delete();
            }
        }

        if ($request->hasFile('images')) {
            $nextSortOrder = (int) $service->images()->max('sort_order') + 1;
            $hasPrimary = $service->images()->where('is_primary', true)->exists();

            foreach ($request->file('images') as $index => $file) {
                $service->images()->create([
                    'path' => $file->store('massage-services', 'public'),
                    'sort_order' => $nextSortOrder + $index,
                    'is_primary' => ! $hasPrimary && $index === 0,
                ]);
            }
        }

        if ($primaryId = $request->integer('primary_image_id')) {
            if ($service->images()->whereKey($primaryId)->exists()) {
                $service->images()->update(['is_primary' => false]);
                $service->images()->whereKey($primaryId)->update(['is_primary' => true]);
            }
        }

        if (! $service->images()->where('is_primary', true)->exists()) {
            $service->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
        }
    }
}
