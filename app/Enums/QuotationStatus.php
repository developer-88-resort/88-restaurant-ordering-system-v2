<?php

namespace App\Enums;

enum QuotationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Converted = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Sent => __('Sent'),
            self::Accepted => __('Customer Accepted'),
            self::Confirmed => __('Confirmed Advance Order'),
            self::Cancelled => __('Cancelled'),
            self::Converted => __('Converted to Order'),
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-700',
            self::Sent => 'bg-blue-100 text-blue-800',
            self::Accepted => 'bg-purple-100 text-purple-800',
            self::Confirmed => 'bg-amber-100 text-amber-800',
            self::Cancelled => 'bg-gray-200 text-gray-600',
            self::Converted => 'bg-green-100 text-green-800',
        };
    }

    /**
     * Only these states may still be edited or converted — everything else
     * is terminal. Preparing/serving of a converted advance order is
     * tracked on the real Order it became, not on the quotation.
     */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Cancelled, self::Converted], true);
    }
}
