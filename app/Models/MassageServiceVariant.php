<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One way a massage service is sold — e.g. "1 hr" at ₱800. A blank price is
 * the menu's "----": listed, but it can't be ordered.
 */
class MassageServiceVariant extends Model
{
    protected $fillable = [
        'massage_service_id',
        'name',
        'description',
        'price',
        'duration_minutes',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_minutes' => 'integer',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(MassageService::class, 'massage_service_id');
    }

    public function isOrderable(): bool
    {
        return $this->price !== null;
    }

    public function durationLabel(): ?string
    {
        return MassageService::formatDuration($this->duration_minutes);
    }
}
