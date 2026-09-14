<?php

namespace App\Models;

use App\Enums\PromotionEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionEvent extends Model
{
    protected $fillable = [
        'promotion_id',
        'event_type',
        'session_id',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => PromotionEventType::class,
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
