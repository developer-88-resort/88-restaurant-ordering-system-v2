<?php

namespace App\Enums;

/**
 * Where a weighing's amount_charged actually came from.
 *
 * 'Typed' is an independent reading — staff saw a number on the scale and
 * keyed it, which is what makes the variance check meaningful evidence
 * that the rate is right. 'Computed' means the "Use expected" shortcut
 * was used instead, for a scale that only displays weight: the figure is
 * just the reference rate echoed back, not a second confirmation of it.
 */
enum AmountSource: string
{
    case Typed = 'typed';
    case Computed = 'computed';

    public function label(): string
    {
        return match ($this) {
            self::Typed => __('Typed'),
            self::Computed => __('Computed from the reference rate'),
        };
    }
}
