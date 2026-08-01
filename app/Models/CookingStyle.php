<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CookingStyle extends Model
{
    protected $fillable = [
        'name',
        'name_ko',
        'surcharge',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'surcharge' => 'decimal:2',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class);
    }

    /**
     * Most styles are free; only a non-zero surcharge ever adds to a line.
     */
    public function isFree(): bool
    {
        return bccomp((string) $this->surcharge, '0.00', 2) === 0;
    }

    /**
     * Korean label when the viewer's locale is Korean, otherwise the
     * Filipino/English name.
     */
    public function displayName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'ko' && $this->name_ko ? $this->name_ko : $this->name;
    }
}
