<?php

namespace App\Enums;

/**
 * Where a weighing's numbers came from.
 *
 * Today every reading is typed by a person from the scale's display. Phase
 * 3 puts the scale on the network so it posts its own figures, and the
 * difference matters for audit: a keyed number can be a typo, a fed one
 * cannot. Recording it now means those lines are already distinguishable
 * when the hardware arrives, instead of the whole history being ambiguous.
 */
enum WeighEntryMode: string
{
    case InPerson = 'in_person';
    case ScaleFeed = 'scale_feed';

    public function label(): string
    {
        return match ($this) {
            self::InPerson => __('In person'),
            self::ScaleFeed => __('Scale feed'),
        };
    }
}
