<?php

namespace App\Http\Controllers;

use App\Enums\SpaceStatus;
use App\Enums\UserRole;
use App\Http\Requests\StoreBulkSpacesRequest;
use App\Http\Requests\StoreSpaceRequest;
use App\Http\Requests\UpdateSpaceRequest;
use App\Models\Area;
use App\Models\Space;
use App\Models\SpaceCategory;
use App\Support\SpaceNaming;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SpaceController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $areas = Area::with([
            'categories' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
            'categories.spaces' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
        ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $activeAreaId = $request->integer('area') ?: $areas->first()?->id;

        return Inertia::render('Spaces/Index', [
            'areas' => $areas->map(fn (Area $area) => $this->areaForFrontend($area))->values(),
            'activeAreaId' => $activeAreaId,
            'canManageSpaces' => in_array($request->user()->role, [UserRole::Superadmin, UserRole::Admin], true),
            'statusOptions' => collect(SpaceStatus::cases())->map(fn (SpaceStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'capsule_class' => $status->capsuleAccentClass(),
            ])->values(),
        ]);
    }

    protected function areaForFrontend(Area $area): array
    {
        $allSpaces = $area->categories->flatMap->spaces;
        $defaultCategory = $area->categories->first();

        return [
            'id' => $area->id,
            'name' => $area->name,
            'default_category_id' => $defaultCategory?->id,
            'spaces' => $allSpaces->values()->map(fn (Space $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'capacity' => $s->capacity,
                'status' => $s->status->value,
                'status_label' => $s->status->label(),
                'status_capsule_class' => $s->status->capsuleAccentClass(),
            ])->values(),
        ];
    }

    /**
     * "New Space" never makes the Superadmin deal with categories directly:
     * given a category, use it as-is; given an area, silently reuse (or
     * create) that area's first category so a Space always has somewhere
     * to belong, since category_id is required at the database level.
     */
    public function create(Request $request): View
    {
        if ($categoryId = $request->integer('category_id')) {
            $category = SpaceCategory::with('area')->findOrFail($categoryId);
        } else {
            $area = Area::findOrFail($request->integer('area_id'));
            $category = $area->categories()->orderBy('sort_order')->orderBy('name')->first()
                ?? SpaceCategory::create(['area_id' => $area->id, 'name' => $area->name, 'is_active' => true]);
            $category->setRelation('area', $area);
        }

        return view('spaces.create', [
            'category' => $category,
            'prefix' => SpaceNaming::prefix($category),
            'nextNumber' => SpaceNaming::nextNumber($category),
        ]);
    }

    public function store(StoreSpaceRequest $request): RedirectResponse
    {
        $category = SpaceCategory::findOrFail($request->integer('category_id'));
        SpaceNaming::problem($request->string('name')->toString(), $category, null, $resolved);
        [$prefix, $number] = $resolved;

        Space::create([
            ...$request->validated(),
            // Saved in the category's own spelling ("kubo  5" → "KUBO 5").
            'name' => SpaceNaming::format($prefix, $number),
            'area_id' => $category->area_id,
            'sort_order' => $number,
        ]);

        return redirect()->route('spaces.index', ['area' => $category->area_id])->with('status', __('Space created successfully.'));
    }

    public function storeBulk(StoreBulkSpacesRequest $request): RedirectResponse
    {
        // The category's existing prefix wins; a typed one only names the
        // first spaces of an empty category — see SpaceNaming.
        $category = SpaceCategory::findOrFail($request->integer('category_id'));
        $prefix = SpaceNaming::prefix($category) ?? $request->string('prefix')->squish()->toString();
        $start = $request->integer('start');
        $count = $request->integer('count');

        $created = 0;
        $skipped = 0;

        for ($n = $start; $n < $start + $count; $n++) {
            if (SpaceNaming::numberTaken($category, $prefix, $n)) {
                $skipped++;

                continue;
            }

            Space::create([
                'category_id' => $category->id,
                'area_id' => $category->area_id,
                'name' => SpaceNaming::format($prefix, $n),
                'sort_order' => $n,
            ]);
            $created++;
        }

        $message = trans_choice(':count space created.|:count spaces created.', $created, ['count' => $created]);
        if ($skipped > 0) {
            $message .= ' '.trans_choice(':count already existed and was skipped.|:count already existed and were skipped.', $skipped, ['count' => $skipped]);
        }

        return redirect()->route('spaces.index', ['area' => $category->area_id])->with('status', $message);
    }

    public function edit(Space $space): View
    {
        $space->load(['category', 'sharedTables']);

        $availableSpaces = Space::where('area_id', $space->area_id)
            ->where('id', '!=', $space->id)
            ->where(function ($query) use ($space) {
                $query->where('status', SpaceStatus::Available)
                    ->orWhereIn('id', $space->sharedTables->pluck('id'));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('spaces.edit', ['space' => $space, 'availableSpaces' => $availableSpaces]);
    }

    public function update(UpdateSpaceRequest $request, Space $space): RedirectResponse
    {
        $attributes = $request->safe()->except(['shared_space_ids', 'status']);

        // A rename is saved in the category's spelling ("kubo 5" → "KUBO 5") and
        // re-sorted by its number; an unchanged legacy name is left as is.
        if (trim($attributes['name']) !== $space->name) {
            SpaceNaming::problem($attributes['name'], $space->category, $space->id, $resolved);
            [$prefix, $number] = $resolved;
            $attributes['name'] = SpaceNaming::format($prefix, $number);
            $attributes['sort_order'] = $number;
        } else {
            $attributes['name'] = $space->name;
        }

        $space->update($attributes);
        $space->syncSharedTables($request->input('shared_space_ids', []));
        $space->setStatusWithSharedTables(SpaceStatus::from($request->string('status')->toString()));

        return redirect()->route('spaces.index', ['area' => $space->area_id])->with('status', __('Space updated successfully.'));
    }

    public function updateStatus(Request $request, Space $space): RedirectResponse
    {
        $request->validate([
            'status' => ['required', new Enum(SpaceStatus::class)],
        ]);

        $space->setStatusWithSharedTables(SpaceStatus::from($request->string('status')->toString()));

        return redirect()->back()
            ->with('status', __('":name" is now :status.', ['name' => $space->name, 'status' => $space->status->label()]));
    }

    public function destroy(Space $space): RedirectResponse
    {
        if ($space->hasActiveOrder()) {
            return redirect()->route('spaces.index', ['area' => $space->area_id])
                ->with('error', __('":name" has an active order and can\'t be deleted.', ['name' => $space->name]));
        }

        $areaId = $space->area_id;
        $space->delete();

        return redirect()->route('spaces.index', ['area' => $areaId])->with('status', __('Space deleted successfully.'));
    }

    public function print(Space $space): View
    {
        return view('spaces.print', ['space' => $space]);
    }

    public function qrCode(Request $request, Space $space): Response
    {
        $result = (new Builder(
            writer: new SvgWriter(),
            data: route('customer.spaces.show', $space->qr_token),
            size: 300,
            margin: 10,
        ))->build();

        $headers = ['Content-Type' => $result->getMimeType()];

        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="space-'.$space->code.'-qr.svg"';
        }

        return response($result->getString(), 200, $headers);
    }
}
