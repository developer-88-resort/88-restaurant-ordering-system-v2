<?php

namespace App\Enums;

/**
 * Never stored — always computed from Promotion's is_published/is_disabled/
 * starts_at/ends_at (see Promotion::status()). Exists as an enum purely so
 * the computed value has a type-safe label()/badgeClasses() pair, matching
 * every other status-like concept in this app.
 */
enum PromotionStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Expired = 'expired';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Scheduled => __('Scheduled'),
            self::Active => __('Active'),
            self::Expired => __('Ended'),
            self::Disabled => __('Disabled'),
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-700',
            self::Scheduled => 'bg-blue-100 text-blue-800',
            self::Active => 'bg-green-100 text-green-800',
            self::Expired => 'bg-red-100 text-red-800',
            self::Disabled => 'bg-gray-200 text-gray-700',
        };
    }
}
