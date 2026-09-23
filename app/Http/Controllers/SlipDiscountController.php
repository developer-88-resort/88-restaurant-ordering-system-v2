<?php

namespace App\Http\Controllers;

use App\Events\KitchenUpdated;
use App\Http\Requests\UpdateSlipDiscountsRequest;
use App\Models\Order;
use App\Services\Printing\OrderSlipTotals;
use Illuminate\Http\JsonResponse;

/**
 * Sets the discounts an order's printed slip shows — picked on the Kitchen
 * Display before printing. Slip only: the receipt's discounts are still
 * chosen at checkout, which never reads these.
 */
class SlipDiscountController extends Controller
{
    public function update(UpdateSlipDiscountsRequest $request, Order $order): JsonResponse
    {
        $entries = $request->entries();

        $order->update(['slip_discounts' => $entries === [] ? null : $entries]);

        // Other tablets on the board show the new slip total too.
        broadcast(new KitchenUpdated());

        $totals = OrderSlipTotals::for($order);

        return response()->json([
            'message' => $entries === []
                ? __('Discount removed from the slip.')
                : __('Slip total is now ₱:total.', ['total' => number_format((float) $totals['total'], 2)]),
            'totals' => $totals,
        ]);
    }
}
