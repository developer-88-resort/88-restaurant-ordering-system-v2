<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\MenuItemAvailability;
use App\Enums\PricingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItem extends Model
{
    use LogsAuditActivity, SoftDeletes;

    protected $fillable = [
        'menu_category_id',
        'name',
        'description',
        'price',
        'pricing_type',
        'price_per_kilo',
        'min_weight_grams',
        'weight_step_grams',
        'allow_tare',
        'counter_only',
        'sku',
        'prep_time_minutes',
        'is_featured',
        'is_best_seller',
        'sort_order',
        'availability_status',
    ];

    /**
     * Mirrors the column defaults. Eloquent's create() doesn't re-fetch the
     * row after insert, so without these a freshly created item would carry
     * a null pricing_type in memory even though the DB applied 'fixed' —
     * and `$item->pricing_type->value` would fatal for the rest of that
     * request. Same reasoning as Setting::current().
     */
    protected $attributes = [
        'pricing_type' => 'fixed',
        'min_weight_grams' => 250,
        'weight_step_grams' => 10,
        'allow_tare' => false,
        'counter_only' => false,
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'pricing_type' => PricingType::class,
            'price_per_kilo' => 'decimal:2',
            'min_weight_grams' => 'integer',
            'weight_step_grams' => 'integer',
            'allow_tare' => 'boolean',
            'counter_only' => 'boolean',
            'prep_time_minutes' => 'integer',
            'is_featured' => 'boolean',
            'is_best_seller' => 'boolean',
            'sort_order' => 'integer',
            'availability_status' => MenuItemAvailability::class,
        ];
    }

    public function menuCategory(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(MenuItemImage::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MenuItemVariant::class)->orderBy('sort_order');
    }

    public function cookingStyles(): BelongsToMany
    {
        return $this->belongsToMany(CookingStyle::class)->orderBy('sort_order');
    }

    public function dailyMarketPrices(): HasMany
    {
        return $this->hasMany(DailyMarketPrice::class);
    }

    public function isPerKilo(): bool
    {
        return $this->pricing_type === PricingType::PerKilo;
    }

    /**
     * The rate to sell this item at today: the market price set for the
     * given day when one exists, otherwise the item's standing
     * price_per_kilo. Returns null for a fixed-price item.
     */
    public function effectivePricePerKilo(?\DateTimeInterface $on = null): ?string
    {
        if (! $this->isPerKilo()) {
            return null;
        }

        $date = ($on ? \Illuminate\Support\Carbon::instance($on) : \Illuminate\Support\Carbon::today())->toDateString();

        $market = $this->dailyMarketPrices()
            ->where('effective_date', $date)
            ->value('price_per_kilo');

        return (string) ($market ?? $this->price_per_kilo);
    }

    public function primaryImage(): ?MenuItemImage
    {
        return $this->images->firstWhere('is_primary', true) ?? $this->images->first();
    }

    public function primaryImageUrl(): ?string
    {
        return $this->primaryImage()?->url;
    }

    public function hasVariants(): bool
    {
        return $this->variants->isNotEmpty();
    }

    /**
     * "₱X.XX" for a plain item, "₱X.XX / kg" for a per-kilo one, or a
     * range/"From" label once it has variants — the base `price` column
     * stops being the sellable price the moment variants exist (each
     * variant carries its own) or the item is sold by weight.
     */
    public function priceRangeLabel(): string
    {
        if ($this->isPerKilo()) {
            return '₱'.number_format((float) $this->price_per_kilo, 2).' / kg';
        }

        if (! $this->hasVariants()) {
            return '₱'.number_format($this->price, 2);
        }

        $prices = $this->variants->pluck('price')->map(fn ($price) => (float) $price);
        $min = $prices->min();
        $max = $prices->max();

        if ($min === $max) {
            return '₱'.number_format($min, 2);
        }

        return __('From ₱:min', ['min' => number_format($min, 2)]);
    }

    protected function auditLabel(): string
    {
        return 'Menu Item';
    }
}
