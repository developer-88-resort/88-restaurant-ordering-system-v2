<?php

namespace App\Enums;

enum OrderPaymentStatus: string
{
    case Recorded = 'recorded';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Recorded => __('Recorded'),
            self::Voided => __('Voided'),
        };
    }
}
