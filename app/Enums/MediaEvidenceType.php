<?php

namespace App\Enums;

enum MediaEvidenceType: string
{
    case Photo = 'photo';
    case Video = 'video';

    public function label(): string
    {
        return match ($this) {
            self::Photo => __('Photo'),
            self::Video => __('Video'),
        };
    }
}
