<?php

namespace App\Enums;

enum OrderItemConfirmationStatus: string
{
    /** Weighed on the scale, waiting for the customer to accept the weight and price. */
    case PendingCustomer = 'pending_customer';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PendingCustomer => __('Waiting for Customer'),
            self::Confirmed => __('Confirmed'),
            self::Rejected => __('Rejected'),
        };
    }
}
