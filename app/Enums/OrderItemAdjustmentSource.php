<?php

namespace App\Enums;

enum OrderItemAdjustmentSource: string
{
    case Kitchen = 'kitchen';
    case OrderManagement = 'order_management';

    public function label(): string
    {
        return match ($this) {
            self::Kitchen => __('Kitchen'),
            self::OrderManagement => __('Order Management'),
        };
    }
}
