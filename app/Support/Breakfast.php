<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Breakfast, as one rule shared by checkout and Reports.
 *
 * An order with any (not cancelled) item from these menu categories is a
 * breakfast order: it is complimentary as a whole — regular menu added to it
 * included — so its only payment method is "Others" (Complimentary), and in
 * Reports it is tallied in the Breakfast section and no other.
 */
class Breakfast
{
    /** @var list<string> */
    public const MENU_CATEGORIES = ['BREAKFAST'];

    /**
     * Breakfast is tallied apart from this moment on (by when the payment was
     * taken). Days before it were tallied with breakfast inside its table's
     * section and keep that.
     */
    public const FROM = '2026-10-08 00:00:00';

    /**
     * The menu items in the breakfast categories.
     *
     * @return Collection<int, int>
     */
    public static function menuItemIds(): Collection
    {
        return DB::table('menu_items')
            ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.menu_category_id')
            ->whereIn('menu_categories.name', self::MENU_CATEGORIES)
            ->pluck('menu_items.id');
    }

    public static function isBreakfastItem(OrderItem $item, ?Collection $breakfastIds = null): bool
    {
        return $item->menu_item_id !== null && ($breakfastIds ?? self::menuItemIds())->contains($item->menu_item_id);
    }

    /**
     * Settled as complimentary (Others only): a breakfast order, from FROM on —
     * before that, breakfast was paid like any other bill.
     */
    public static function isComplimentary(Order $order): bool
    {
        return now()->gte(self::FROM) && self::isBreakfastOrder($order);
    }

    /** Whether the order still has a breakfast item that wasn't cancelled. */
    public static function isBreakfastOrder(Order $order): bool
    {
        $ids = self::menuItemIds();
        if ($ids->isEmpty()) {
            return false;
        }

        $order->loadMissing('items.adjustments');

        return $order->items->contains(fn (OrderItem $item) => self::isBreakfastItem($item, $ids) && ! $item->isFullyCancelled());
    }
}
