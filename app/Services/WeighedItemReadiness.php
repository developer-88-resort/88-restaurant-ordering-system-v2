<?php

namespace App\Services;

use App\Models\MenuItem;

/**
 * Why a per-kilo item isn't ready to be weighed, as a structured list —
 * each reason carries a URL that actually fixes it, replacing the old
 * hardcoded "Finish setup" link into the item edit page (which couldn't
 * fix a missing cooking-style assignment; that's on /weigh/items now).
 *
 * Deliberately does NOT check availability_status — Out of Stock/Hidden is
 * a normal merchandising state an admin chooses, not a setup gap, and the
 * weigh wizard's item list already excludes non-available/seasonal items
 * before this ever runs.
 */
class WeighedItemReadiness
{
    /**
     * @return array<int, array{code: string, label: string, url: string}>
     */
    public static function reasons(MenuItem $item): array
    {
        if (! $item->isPerKilo()) {
            return [];
        }

        $reasons = [];
        $editUrl = route('menu-items.edit', $item);

        if ($item->price_per_kilo === null || (float) $item->price_per_kilo <= 0) {
            $reasons[] = ['code' => 'no_rate', 'label' => __('No price per kilo set'), 'url' => $editUrl];
        }

        if ($item->min_weight_grams === null || (int) $item->min_weight_grams <= 0) {
            $reasons[] = ['code' => 'no_minimum', 'label' => __('No minimum weight set'), 'url' => $editUrl];
        }

        if ($item->resolvedCookingStyles()->isEmpty()) {
            $reasons[] = $item->cooking_style_set_id
                ? [
                    'code' => 'set_empty',
                    'label' => __('Assigned style set has no active styles'),
                    'url' => route('weigh.cooking-styles.edit', $item->cooking_style_set_id),
                ]
                : [
                    'code' => 'no_set',
                    'label' => __('No cooking styles assigned'),
                    'url' => route('weigh.items.index'),
                ];
        }

        return $reasons;
    }
}
