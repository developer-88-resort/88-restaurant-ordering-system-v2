<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One service on the Massage department's list (Massage Services) — its own
 * table, never a restaurant menu item. Archived, not deleted, so past
 * orders keep pointing at it. The description is the same rich text a menu
 * item's is (Massage/ServiceForm.jsx uses the menu's editor).
 */
class MassageService extends Model
{
    use LogsAuditActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'price',
        'duration_minutes',
        'is_available',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_minutes' => 'integer',
            'is_available' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(MassageServiceImage::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MassageServiceVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function addOns(): HasMany
    {
        return $this->hasMany(MassageServiceAddOn::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Variants that can be ordered (they have a price). */
    public function orderableVariants()
    {
        return $this->variants->filter(fn (MassageServiceVariant $variant) => $variant->isOrderable())->values();
    }

    /**
     * "₱800.00", or "₱500.00 – ₱1,100.00" across its variants — the way a
     * menu item with variants shows its price.
     */
    public function priceLabel(): string
    {
        $prices = $this->orderableVariants()->map(fn ($variant) => (float) $variant->price);

        if ($prices->isEmpty()) {
            return '₱'.number_format((float) $this->price, 2);
        }

        $min = $prices->min();
        $max = $prices->max();

        return $min === $max ? '₱'.number_format($min, 2) : '₱'.number_format($min, 2).' – ₱'.number_format($max, 2);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }

    /** The list order: Sort Order first, then name. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function primaryImageUrl(): ?string
    {
        $image = $this->images->firstWhere('is_primary', true) ?? $this->images->first();

        return $image?->url;
    }

    /** "1 hr 30 mins", "45 mins", "2 hrs" — the form's picker says the same. */
    public function durationLabel(): ?string
    {
        return self::formatDuration($this->duration_minutes);
    }

    public static function formatDuration(?int $totalMinutes): ?string
    {
        if (! $totalMinutes) {
            return null;
        }

        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        return trim(implode(' ', array_filter([
            $hours ? $hours.' '.($hours === 1 ? __('hr') : __('hrs')) : null,
            $minutes ? $minutes.' '.($minutes === 1 ? __('min') : __('mins')) : null,
        ])));
    }

    /** The description as plain text, for cards and the order screen. */
    public function descriptionText(): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '</li>', '<br>'], ' ', (string) $this->description)))));
    }

    protected function auditLabel(): string
    {
        return 'Massage Service';
    }
}
