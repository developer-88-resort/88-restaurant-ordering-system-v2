<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\QuotationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An advance order / quotation pinned to a table (or area/category) ahead
 * of time. Items carry frozen quoted prices; conversion copies them into
 * a real Order exactly once — `converted_order_id` is both the link and
 * the double-conversion guard.
 */
class Quotation extends Model
{
    use LogsAuditActivity;

    protected $fillable = [
        'quotation_number',
        'request_id',
        'area_id',
        'space_category_id',
        'space_id',
        'customer_name',
        'customer_contact',
        'scheduled_for',
        'status',
        'subtotal',
        'notes',
        'converted_order_id',
        'created_by',
        'accepted_at',
        'confirmed_at',
        'converted_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'status' => QuotationStatus::class,
            'subtotal' => 'decimal:2',
            'accepted_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'converted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function spaceCategory(): BelongsTo
    {
        return $this->belongsTo(SpaceCategory::class, 'space_category_id');
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recalculateSubtotal(): void
    {
        $this->update(['subtotal' => $this->items()->sum('subtotal')]);
    }

    protected function auditLabel(): string
    {
        return 'Quotation';
    }

    protected function auditIdentifier(): string
    {
        return $this->quotation_number;
    }
}
