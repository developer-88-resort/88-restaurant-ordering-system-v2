<?php

namespace App\Enums;

/**
 * An advance order's whole lifecycle, start to finish: it's being put
 * together (Draft), it has landed on a real receipt as a batch of items
 * (Added), or it never went anywhere (Cancelled). The old four-state
 * Sent/Accepted/Confirmed/Converted pipeline is gone — the one-shot create
 * screen creates the quotation and appends it to the chosen order in the
 * same action, so a quotation goes Draft -> Added (or Cancelled) with
 * nothing manual in between. `Added`'s DB value stays `'converted'` on
 * purpose: every quotation that reached the old `Converted` state already
 * means exactly the same thing and needed no data rewrite.
 */
enum QuotationStatus: string
{
    case Draft = 'draft';
    case Added = 'converted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Added => __('Added to Order'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-700',
            self::Added => 'bg-green-100 text-green-800',
            self::Cancelled => 'bg-gray-200 text-gray-600',
        };
    }

    /**
     * Only a Draft can still be added to an order — Added/Cancelled are
     * both terminal. Preparing/serving of an added advance order is tracked
     * on the real Order it joined, not on the quotation.
     */
    public function isOpen(): bool
    {
        return $this === self::Draft;
    }
}
