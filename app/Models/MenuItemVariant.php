<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MenuItemVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'menu_item_id',
        'name',
        'description',
        'sku',
        'price',
        'image_path',
        'sort_order',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sort_order' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    /**
     * Falls back to the parent item's own primary image when this variant
     * has no photo of its own — most variants (Solo/Medium/Large) look the
     * same as the base dish and don't need a separate picture; only ones
     * that genuinely look different (Spicy vs Oriental) need to set one.
     */
    public function imageUrl(): ?string
    {
        return $this->image_path
            ? Storage::disk('public')->url($this->image_path)
            : $this->menuItem?->primaryImageUrl();
    }
}
