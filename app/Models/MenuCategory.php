<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuCategory extends Model
{
    use LogsAuditActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    /**
     * Counts each variant as its own orderable option instead of counting a
     * variant-bearing item once — a customer looking at "Soups (1)" would
     * assume there's a single dish, when a variant item like that might
     * really be 9 different soups to choose from.
     */
    public function orderableOptionCount(): int
    {
        return $this->menuItems->sum(fn (MenuItem $item) => $item->hasVariants() ? $item->variants->count() : 1);
    }

    protected function auditLabel(): string
    {
        return 'Menu Category';
    }
}
