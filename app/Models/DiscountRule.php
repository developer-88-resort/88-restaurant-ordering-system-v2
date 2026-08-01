<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\DiscountCalculationMode;
use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DiscountRule extends Model
{
    use LogsAuditActivity;

    protected $fillable = [
        'name',
        'code',
        'calculation_mode',
        'value',
        'is_custom_value',
        'statutory_type',
        'scope',
        'is_stackable',
        'priority',
        'max_discount_amount',
        'min_bill_amount',
        'requires_customer_id',
        'requires_reason',
        'requires_manager_approval',
        'active_from',
        'active_until',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'calculation_mode' => DiscountCalculationMode::class,
            'value' => 'decimal:2',
            'is_custom_value' => 'boolean',
            'statutory_type' => DiscountType::class,
            'is_stackable' => 'boolean',
            'priority' => 'integer',
            'max_discount_amount' => 'decimal:2',
            'min_bill_amount' => 'decimal:2',
            'requires_customer_id' => 'boolean',
            'requires_reason' => 'boolean',
            'requires_manager_approval' => 'boolean',
            'active_from' => 'date',
            'active_until' => 'date',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Rules a cashier may offer right now: switched on AND inside their
     * configured date window (open-ended when either bound is null).
     */
    public function scopeCurrentlyAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('active_from')->orWhereDate('active_from', '<=', today()))
            ->where(fn (Builder $q) => $q->whereNull('active_until')->orWhereDate('active_until', '>=', today()));
    }

    public function isStatutory(): bool
    {
        return $this->statutory_type !== null;
    }

    protected function auditLabel(): string
    {
        return 'Discount Rule';
    }

    protected function auditIdentifier(): string
    {
        return $this->name;
    }
}
