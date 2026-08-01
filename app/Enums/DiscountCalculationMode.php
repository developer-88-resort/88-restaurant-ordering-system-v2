<?php

namespace App\Enums;

enum DiscountCalculationMode: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percent => __('Percentage'),
            self::Fixed => __('Fixed Amount'),
        };
    }
}
