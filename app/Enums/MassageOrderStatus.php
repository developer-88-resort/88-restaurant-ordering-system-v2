<?php

namespace App\Enums;

/**
 * A massage order is open until it is paid. Paid is the end of it; an open
 * one can be cancelled instead, and voiding its payment opens it again.
 * Colours follow the restaurant's OrderStatus.
 */
enum MassageOrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Unpaid'),
            self::Paid => __('Paid'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800',
            self::Paid => 'bg-green-100 text-green-800',
            self::Cancelled => 'bg-gray-200 text-gray-700',
        };
    }

    public function dotClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-500',
            self::Paid => 'bg-green-500',
            self::Cancelled => 'bg-gray-500',
        };
    }
}
