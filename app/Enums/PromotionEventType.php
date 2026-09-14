<?php

namespace App\Enums;

enum PromotionEventType: string
{
    case View = 'view';
    case Click = 'click';

    public function label(): string
    {
        return match ($this) {
            self::View => __('View'),
            self::Click => __('Click'),
        };
    }
}
