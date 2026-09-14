<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\DiscountCalculationMode;
use App\Enums\PromotionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Promotion extends Model
{
    use LogsAuditActivity;

    protected $fillable = [
        'image_path',
        'mobile_image_path',
        'starts_at',
        'ends_at',
        'is_published',
        'is_disabled',
        'banner_cta_url',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_calculation_mode' => DiscountCalculationMode::class,
            'discount_value' => 'decimal:2',
            'discount_min_order_amount' => 'decimal:2',
            'discount_max_discount_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_published' => 'boolean',
            'is_disabled' => 'boolean',
            'is_featured' => 'boolean',
            'display_priority' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Promotion $promotion) {
            $promotion->code ??= 'TMP-'.Str::random(12);
        });

        // The human-readable code is derived from the row's own
        // auto-increment id, so it can only be known after the insert —
        // same two-step pattern Quotation uses. Direct attribute
        // assignment + saveQuietly() (not update()/fill()) because `code`
        // is deliberately absent from $fillable — a plain fill() would
        // silently drop it. saveQuietly() also skips Eloquent's model
        // events entirely, so this doesn't fire a spurious "Updated
        // Promotion" audit-log row a moment after "Created".
        static::created(function (Promotion $promotion) {
            if (str_starts_with($promotion->code, 'TMP-')) {
                $promotion->code = sprintf('PRM-%d-%04d', $promotion->created_at->year, $promotion->id);
                $promotion->saveQuietly();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PromotionEvent::class);
    }

    /**
     * Never stored — is_disabled overrides everything else, then it's
     * Draft/Scheduled/Active/Expired purely from the publish flag and the
     * start/end window, evaluated against the app's configured timezone.
     */
    protected function status(): Attribute
    {
        return Attribute::get(function (): PromotionStatus {
            if ($this->is_disabled) {
                return PromotionStatus::Disabled;
            }

            if (! $this->is_published) {
                return PromotionStatus::Draft;
            }

            $now = now();

            if ($this->starts_at && $this->starts_at->isAfter($now)) {
                return PromotionStatus::Scheduled;
            }

            if ($this->ends_at && $this->ends_at->isBefore($now)) {
                return PromotionStatus::Expired;
            }

            return PromotionStatus::Active;
        });
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image_path ? Storage::disk('public')->url($this->image_path) : null);
    }

    protected function mobileImageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->mobile_image_path ? Storage::disk('public')->url($this->mobile_image_path) : null);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('is_disabled', false)->where('is_published', false);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('is_disabled', false)
            ->where('is_published', true)
            ->whereNotNull('starts_at')
            ->where('starts_at', '>', now());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_disabled', false)
            ->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('is_disabled', false)
            ->where('is_published', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now());
    }

    public function scopeDisabled(Builder $query): Builder
    {
        return $query->where('is_disabled', true);
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'draft' => $query->draft(),
            'scheduled' => $query->scheduled(),
            'active' => $query->active(),
            'expired' => $query->expired(),
            'disabled' => $query->disabled(),
            default => $query,
        };
    }

    /**
     * Backs the banner grid's live-rotation preview. Every promotion is a
     * banner now, so this is just "active" — no separate featured flag.
     */
    public function scopeFeaturedLive(Builder $query): Builder
    {
        return $query->active()
            ->orderBy('display_priority')
            ->orderByDesc('starts_at');
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('code', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    /**
     * At creation-audit time `code` is still the `TMP-` placeholder (see
     * booted() above), so `id` — not `code` — is the fallback that's
     * guaranteed known and won't bake a placeholder into the log forever.
     */
    protected function auditIdentifier(): string
    {
        return $this->title ?: 'Banner #'.$this->id;
    }

    /**
     * Single source of truth for every *post-creation* human-readable
     * label (flash messages, next-scheduled stat, etc.) — unlike
     * auditIdentifier(), code is always fully resolved by this point.
     */
    public function displayLabel(): string
    {
        return $this->title ?: $this->code;
    }
}
