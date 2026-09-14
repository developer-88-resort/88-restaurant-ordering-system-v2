<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\MenuItemAvailability;
use App\Enums\PricingType;
use App\Services\WeighedItemReadiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

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
        'cooking_style_set_id',
        'weighed_sort_order',
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

    public function addOns(): HasMany
    {
        return $this->hasMany(MenuItemAddOn::class)->orderBy('sort_order');
    }

    /**
     * A per-item OVERRIDE — most per-kilo items have no rows here and
     * resolve their styles through cookingStyleSet() instead. See
     * resolvedCookingStyles(), the one place that decision is made.
     */
    public function cookingStyles(): BelongsToMany
    {
        return $this->belongsToMany(CookingStyle::class)->orderBy('sort_order');
    }

    public function cookingStyleSet(): BelongsTo
    {
        return $this->belongsTo(CookingStyleSet::class);
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
     * Which cooking styles this item actually offers — an explicit
     * per-item override (cookingStyles(), i.e. rows in the
     * cooking_style_menu_item pivot) wins outright as a full replacement,
     * never a merge; otherwise falls back to the assigned CookingStyleSet.
     * This is the ONE place that decision is made — the weigh wizard, the
     * item form preview, receipts/order validation, and readiness checks
     * all call this rather than re-deriving it.
     */
    public function resolvedCookingStyles(): Collection
    {
        $overrides = $this->relationLoaded('cookingStyles')
            ? $this->cookingStyles->where('is_active', true)->values()
            : $this->cookingStyles()->where('is_active', true)->get();

        if ($overrides->isNotEmpty()) {
            return $overrides;
        }

        if (! $this->cookingStyleSet || ! $this->cookingStyleSet->is_active) {
            return collect();
        }

        return $this->cookingStyleSet->relationLoaded('cookingStyles')
            ? $this->cookingStyleSet->cookingStyles->where('is_active', true)->values()
            : $this->cookingStyleSet->cookingStyles()->where('is_active', true)->get();
    }

    /**
     * A per-kilo item missing a rate, a minimum weight, or with no cooking
     * styles to offer. The weigh station dead-ends on these — so the menu
     * list flags them loudly rather than letting staff discover it
     * mid-service. See WeighedItemReadiness for the structured,
     * deep-linkable version of this check (what this delegates to).
     */
    public function needsWeighedSetup(): bool
    {
        return WeighedItemReadiness::reasons($this) !== [];
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

    public function hasAddOns(): bool
    {
        return $this->addOns->isNotEmpty();
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

    /**
     * A short, tag-free preview of the (now rich-text HTML) description —
     * for the admin grid card and the customer menu card, neither of which
     * should ever show raw markup as literal text.
     */
    public function plainDescription(int $limit = 160): ?string
    {
        if (! $this->description) {
            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($this->description), ENT_QUOTES)));

        return $text === '' ? null : \Illuminate\Support\Str::limit($text, $limit);
    }

    protected function auditLabel(): string
    {
        return 'Menu Item';
    }
}
