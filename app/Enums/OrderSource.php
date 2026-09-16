<?php

namespace App\Enums;

enum OrderSource: string
{
    case Staff = 'staff';
    case Qr = 'qr';

    public function label(): string
    {
        return match ($this) {
            self::Staff => __('Staff'),
            self::Qr => __('QR / Customer'),
        };
    }
}
