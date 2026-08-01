<?php

namespace App\Enums;

enum PricingType: string
{
    case Fixed = 'fixed';
    case PerKilo = 'per_kilo';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('Fixed Price'),
            self::PerKilo => __('Priced per Kilo'),
        };
    }
}
