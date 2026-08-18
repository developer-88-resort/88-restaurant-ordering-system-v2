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
        'counter_only' => false,
    ];

    /**
     * A per-kilo item is, by definition, handed over at the counter: it has
     * to be put on a scale in front of someone. Forcing the flag here
     * rather than trusting the form means it holds for seeders, imports and
     * direct API posts too — the UI shows it locked, this makes it true.
     */
    protected static function booted(): void
    {
        static::saving(function (MenuItem $item) {
            if ($item->pricing_type === PricingType::PerKilo) {
                $item->counter_only = true;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'pricing_type' => PricingType::class,
            'price_per_kilo' => 'decimal:2',
            'min_weight_grams' => 'integer',
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
     * A per-kilo item that predates the "at least one cooking style" rule,
     * or that lost its rate. The weigh station dead-ends on these — its
     * cooking step has nothing to offer — so the menu list flags them
     * loudly rather than letting staff discover it mid-service.
     */
    public function needsWeighedSetup(): bool
    {
        if (! $this->isPerKilo()) {
            return false;
        }

        // Uses the loaded relation when the caller eager-loaded it (the menu
        // list renders this for every row), falling back to a count query.
        $styleCount = $this->relationLoaded('cookingStyles')
            ? $this->cookingStyles->count()
            : $this->cookingStyles()->count();

        return $this->price_per_kilo === null
            || (float) $this->price_per_kilo <= 0
            || $styleCount === 0;
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
