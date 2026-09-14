<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable, named bundle of cooking styles (e.g. "Seafood", "Meat"),
 * owned by Weigh & Order and assigned to per-kilo items via
 * MenuItem::cooking_style_set_id. Decoupled from menu categories — two
 * items in different categories can share a set, two items in the same
 * category can use different sets.
 */
class CookingStyleSet extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function cookingStyles(): BelongsToMany
    {
        return $this->belongsToMany(CookingStyle::class, 'cooking_style_set_style')->orderBy('sort_order');
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }
}
