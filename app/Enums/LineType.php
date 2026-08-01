<?php

namespace App\Enums;

enum LineType: string
{
    case Fixed = 'fixed';
    case Weighed = 'weighed';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('Fixed'),
            self::Weighed => __('Weighed'),
        };
    }
}
