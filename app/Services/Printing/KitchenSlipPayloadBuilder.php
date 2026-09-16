<?php

namespace App\Services\Printing;

use App\Models\Order;

/**
 * Flattens an Order into plain text lines for the thermal printer bridge.
 * Mirrors resources/views/orders/partials/kitchen-slip-print-body.blade.php
 * (the browser-print version) field-for-field, but the bridge — running as
 * a bare PHP process on the resort's LAN, not a Laravel request — never
 * touches the Order model or translations itself; it only prints whatever
 * lines this builder already resolved server-side.
 */
class KitchenSlipPayloadBuilder
{
    public static function build(Order $order): array
    {
        $order->loadMissing(['guestSession', 'creator', 'sourceQuotation', 'items.cookingStyle']);

        $meta = [
            [__('Order No.'), $order->orderNumber()],
            [__('Date'), $order->created_at->format('M d, Y g:i A')],
            [__('Location'), $order->locationLabel()],
        ];

        if ($order->isStaffCreated()) {
            if ($order->guestSession) {
                $meta[] = [__('Guest'), $order->guestSession->displayLabel()];
            }
            if ($order->customer_name) {
                $meta[] = [__('Customer'), $order->customer_name];
            }
        }

        $meta[] = [__('Order Type'), $order->order_type->label()];

        if ($order->pax) {
            $meta[] = [__('Pax'), (string) $order->pax];
        }

        $meta[] = $order->isStaffCreated()
            ? [__('Waiter'), $order->creator->name ?? __('Unknown')]
            : [__('Ordered By'), $order->customer_name ?: $order->guestSession?->displayLabel() ?: __('Customer')];

        $itemsByBatch = $order->items->groupBy('batch_number');
        $hasMultipleBatches = $itemsByBatch->count() > 1;

        $batches = $itemsByBatch->map(function ($batchItems, $batchNumber) use ($hasMultipleBatches) {
            return [
                'label' => $hasMultipleBatches
                    ? ($batchNumber ? __('Batch').' #'.$batchNumber : __('Earlier round'))
                    : null,
                'items' => $batchItems->map(function ($item) {
                    $cancelled = $item->isFullyCancelled();
                    $qty = $item->isWeighed()
                        ? number_format((float) $item->netWeightGrams()).'g'
                        : $item->quantity.'x';

                    $subLines = [];
                    if ($item->isWeighed() && $item->cookingLabel()) {
                        $subLines[] = $item->cookingLabel();
                    }
                    if ($cancelled) {
                        $subLines[] = $item->isWeighed() ? __('VOIDED') : __('CANCELLED');
                    } elseif ($item->cancelledQuantity() > 0) {
                        $subLines[] = '-'.$item->cancelledQuantity().' '.__('cancelled');
                    }
                    if ($item->cooking_note) {
                        $subLines[] = $item->cooking_note;
                    }
                    if ($item->notes) {
                        $subLines[] = __('Note').': '.$item->notes;
                    }

                    return [
                        'line' => "{$qty} {$item->item_name}",
                        'cancelled' => $cancelled,
                        'sub_lines' => $subLines,
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return [
            // The browser-print page, rendered here and carried along so the
            // bridge can screenshot it and print that image — which is what
            // makes Direct Print come out identical to the Print button. The
            // text fields below stay populated as the fallback for when the
            // bridge machine can't produce an image.
            'html' => view('orders.kitchen-slip-print', [
                'order' => $order,
                'paperWidth' => '80mm',
                'forImage' => true,
            ])->render(),
            'title' => __('Kitchen Order Slip'),
            'advance_order_label' => $order->sourceQuotation
                ? __('Advance Order').' · '.$order->sourceQuotation->quotation_number
                : null,
            'meta' => $meta,
            'batches' => $batches,
            'notes' => $order->notes,
            'notes_label' => __('Order Notes'),
            'footer' => __('Kitchen copy — not valid as receipt.'),
        ];
    }
}
