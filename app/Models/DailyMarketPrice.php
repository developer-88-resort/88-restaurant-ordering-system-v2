<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class DailyMarketPrice extends Model
{
    protected $fillable = [
        'menu_item_id',
        'price_per_kilo',
        'effective_date',
        'set_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'price_per_kilo' => 'decimal:2',
            'effective_date' => 'date',
        ];
    }

    /**
     * Always store the effective date as a bare 'Y-m-d'.
     *
     * The `date` cast otherwise serializes it with the model's full
     * datetime format ('2026-08-01 00:00:00'). MySQL silently truncates
     * that on the way into a DATE column, but SQLite (what the test suite
     * runs on) keeps the string verbatim — so an exact-match lookup on
     * '2026-08-01' finds nothing, the (menu_item_id, effective_date) unique
     * index stops catching duplicates, and updateOrCreate inserts a second
     * row instead of updating the first. Normalizing on write keeps the
     * column identical on both engines.
     */
    protected function effectiveDate(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value === null ? null : Carbon::parse($value)->toDateString(),
        );
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by_user_id');
    }
}
