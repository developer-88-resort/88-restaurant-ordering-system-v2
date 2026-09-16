<?php

namespace App\Models;

use App\Enums\PrinterJobStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrinterJob extends Model
{
    protected $fillable = [
        'type',
        'order_id',
        'payload',
        'status',
        'attempts',
        'error_message',
        'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => PrinterJobStatus::class,
            'printed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
