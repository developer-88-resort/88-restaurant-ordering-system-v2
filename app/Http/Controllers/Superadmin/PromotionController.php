<?php

namespace App\Http\Controllers\Superadmin;

use App\Enums\PromotionEventType;
use App\Enums\PromotionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromotionRequest;
use App\Models\Promotion;
use App\Models\PromotionEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PromotionController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $promotions = $this->filteredQuery($request)
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Promotion $promotion) => $this->decorate($promotion));

        $nextScheduled = Promotion::scheduled()->orderBy('starts_at')->first();

        return Inertia::render('Promotions/Index', [
            'filters' => $request->only(['search', 'status', 'date_from', 'date_to']),
            'promotions' => $promotions,
            'stats' => [
                'active' => Promotion::active()->count(),
                'active_new_this_week' => Promotion::active()->where('created_at', '>=', now()->subDays(7))->count(),
                'scheduled' => Promotion::scheduled()->count(),
                'next_scheduled_title' => $nextScheduled?->displayLabel(),
                'views' => PromotionEvent::where('event_type', PromotionEventType::View->value)->count(),
                'views_trend' => $this->eventCountTrendPercent(PromotionEventType::View),
                'clicks' => PromotionEvent::where('event_type', PromotionEventType::Click->value)->count(),
                'clicks_trend' => $this->eventCountTrendPercent(PromotionEventType::Click),
            ],
            'currentDateTime' => now()->translatedFormat('l, F j, Y \a\t g:i A'),
            'statusOptions' => $this->statusOptionsForFrontend(),
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('Promotions/Create');
    }

    public function store(PromotionRequest $request): RedirectResponse
    {
        $promotion = DB::transaction(function () use ($request) {
            $promotion = Promotion::create([
                'starts_at' => $request->input('starts_at'),
                'ends_at' => $request->input('ends_at'),
                'is_published' => $request->boolean('is_published'),
                'is_disabled' => $request->boolean('is_disabled'),
                'banner_cta_url' => $request->input('banner_cta_url'),
                'created_by' => $request->user()?->id,
            ]);

            $this->storeUploadedImage($request, $promotion);
            $promotion->save();

            return $promotion;
        });

        return redirect()->route('superadmin.promotions.index')
            ->with('status', __('":title" was created.', ['title' => $promotion->displayLabel()]));
    }

    public function show(Promotion $promotion): InertiaResponse
    {
        $promotion->load('creator');

        return Inertia::render('Promotions/Show', [
            'promotion' => $this->decorate($promotion, detailed: true),
        ]);
    }

    public function edit(Promotion $promotion): InertiaResponse
    {
        return Inertia::render('Promotions/Edit', [
            'promotion' => $this->decorate($promotion, detailed: true),
        ]);
    }

    /**
     * Never touches title, type, description, or created_at — only the
     * fillable banner mechanics. Any legacy content on older rows stays
     * frozen exactly as it was.
     */
    public function update(PromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        DB::transaction(function () use ($request, $promotion) {
            $promotion->update([
                'starts_at' => $request->input('starts_at'),
                'ends_at' => $request->input('ends_at'),
                'is_published' => $request->boolean('is_published'),
                'is_disabled' => $request->boolean('is_disabled'),
                'banner_cta_url' => $request->input('banner_cta_url'),
            ]);

            $this->storeUploadedImage($request, $promotion);
            $promotion->save();
        });

        return redirect()->route('superadmin.promotions.index')
            ->with('status', __('":title" was updated.', ['title' => $promotion->displayLabel()]));
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        if ($promotion->image_path) {
            Storage::disk('public')->delete($promotion->image_path);
        }
        if ($promotion->mobile_image_path) {
            Storage::disk('public')->delete($promotion->mobile_image_path);
        }

        $label = $promotion->displayLabel();
        $promotion->delete();

        return redirect()->back()
            ->with('status', __('":title" was deleted.', ['title' => $label]));
    }

    /**
     * The card's Activate/Deactivate action — flips the manual override,
     * distinct from the create/edit form's "Published" toggle.
     */
    public function toggleStatus(Promotion $promotion): RedirectResponse
    {
        $promotion->update(['is_disabled' => ! $promotion->is_disabled]);

        return redirect()->back()->with('status', $promotion->is_disabled
            ? __('":title" was disabled.', ['title' => $promotion->displayLabel()])
            : __('":title" was re-enabled.', ['title' => $promotion->displayLabel()]));
    }

    protected function filteredQuery(Request $request): Builder
    {
        return Promotion::query()
            ->withCount([
                'events as views_count' => fn ($q) => $q->where('event_type', PromotionEventType::View->value),
                'events as clicks_count' => fn ($q) => $q->where('event_type', PromotionEventType::Click->value),
            ])
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->withStatus($request->string('status')->toString()))
            ->when($request->filled('date_from'), fn ($q) => $q->where(
                fn ($q2) => $q2->whereNull('starts_at')->orWhereDate('starts_at', '>=', $request->date('date_from'))
            ))
            ->when($request->filled('date_to'), fn ($q) => $q->where(
                fn ($q2) => $q2->whereNull('ends_at')->orWhereDate('ends_at', '<=', $request->date('date_to'))
            ));
    }

    /**
     * Real week-over-week change, from actual event timestamps — this
     * table is empty until a customer-facing surface starts recording
     * into it, so this correctly returns null (no comparison to draw)
     * rather than a fabricated number.
     */
    protected function eventCountTrendPercent(PromotionEventType $type): ?float
    {
        $thisWeek = PromotionEvent::where('event_type', $type->value)->where('created_at', '>=', now()->subDays(7))->count();
        $lastWeek = PromotionEvent::where('event_type', $type->value)
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
            ->count();

        if ($lastWeek === 0) {
            return null;
        }

        return round((($thisWeek - $lastWeek) / $lastWeek) * 100, 1);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decorate(Promotion $promotion, bool $detailed = false): array
    {
        $status = $promotion->status;
        $views = (int) ($promotion->views_count ?? $promotion->events()->where('event_type', PromotionEventType::View->value)->count());
        $clicks = (int) ($promotion->clicks_count ?? $promotion->events()->where('event_type', PromotionEventType::Click->value)->count());

        $base = [
            'id' => $promotion->id,
            'code' => $promotion->code,
            'title' => $promotion->title,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_badge_classes' => $status->badgeClasses(),
            'image_url' => $promotion->image_url,
            'mobile_image_url' => $promotion->mobile_image_url,
            'starts_at' => $promotion->starts_at?->toIso8601String(),
            'ends_at' => $promotion->ends_at?->toIso8601String(),
            'schedule_text' => $this->scheduleText($promotion, $status->value),
            'is_published' => $promotion->is_published,
            'is_disabled' => $promotion->is_disabled,
            'banner_cta_url' => $promotion->banner_cta_url,
            'views_count' => $views,
            'clicks_count' => $clicks,
            'ctr' => $views > 0 ? round($clicks / $views * 100, 1) : null,
        ];

        if (! $detailed) {
            return $base;
        }

        return $base + [
            'description' => $promotion->description,
            'creator_name' => $promotion->creator?->name,
            'created_at' => $promotion->created_at->toIso8601String(),
            'updated_at' => $promotion->updated_at->toIso8601String(),
        ];
    }

    protected function scheduleText(Promotion $promotion, string $status): string
    {
        $now = now();

        return match ($status) {
            'scheduled' => $this->relativeDaysText(
                (int) $now->startOfDay()->diffInDays($promotion->starts_at->copy()->startOfDay(), true),
                __('Starts today'), 'Starts in :count day', 'Starts in :count days',
            ),
            'active' => $promotion->ends_at
                ? $this->relativeDaysText(
                    (int) $now->startOfDay()->diffInDays($promotion->ends_at->copy()->startOfDay(), true),
                    __('Ends today'), ':count day left', ':count days left',
                )
                : __('Ongoing'),
            'expired' => __('Ended'),
            default => __('Not published'),
        };
    }

    protected function relativeDaysText(int $days, string $todayLabel, string $singular, string $plural): string
    {
        if ($days < 1) {
            return $todayLabel;
        }

        return trans_choice("{$singular}|{$plural}", $days, ['count' => $days]);
    }

    protected function storeUploadedImage(Request $request, Promotion $promotion): void
    {
        if ($request->boolean('remove_image') && $promotion->image_path) {
            Storage::disk('public')->delete($promotion->image_path);
            $promotion->image_path = null;
        }

        if ($request->hasFile('image')) {
            if ($promotion->image_path) {
                Storage::disk('public')->delete($promotion->image_path);
            }
            $promotion->image_path = $request->file('image')->store('promotions', 'public');
        }

        if ($request->boolean('remove_mobile_image') && $promotion->mobile_image_path) {
            Storage::disk('public')->delete($promotion->mobile_image_path);
            $promotion->mobile_image_path = null;
        }

        if ($request->hasFile('mobile_image')) {
            if ($promotion->mobile_image_path) {
                Storage::disk('public')->delete($promotion->mobile_image_path);
            }
            $promotion->mobile_image_path = $request->file('mobile_image')->store('promotions/mobile', 'public');
        }
    }

    /**
     * @return array<int, array{value: string, label: string, badgeClasses: string}>
     */
    protected function statusOptionsForFrontend(): array
    {
        return array_map(fn (PromotionStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
            'badgeClasses' => $status->badgeClasses(),
        ], PromotionStatus::cases());
    }
}
