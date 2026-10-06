<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An extra on a massage service — e.g. Hot Stone, ₱300. */
class MassageServiceAddOn extends Model
{
    protected $fillable = [
        'massage_service_id',
        'name',
        'description',
        'price',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(MassageService::class, 'massage_service_id');
    }
}
