<?php

namespace App\Http\Resources;

use App\Services\OrderTotals;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The one JSON shape for an order, whether it's the single "here's the
 * bill" preview or one entry in a list of a table's open bills. Every
 * consumer gets `items[]` (never omitted) and numeric money fields — no
 * more picking-list entries that are missing the fields the detail view
 * assumes are there.
 *
 * @mixin \App\Models\Order
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['items.adjustments', 'items.cookingStyle', 'space', 'sourceQuotation']);

        $totals = OrderTotals::for($this->resource);

        return [
            'id' => $this->id,
            'order_number' => $this->orderNumber(),
            'status' => $this->status->value,
            'payment_status' => $this->payment_status->value,
            'space' => $this->space ? ['id' => $this->space->id, 'name' => $this->space->name] : null,
            'guest_label' => $this->customer_name ?: __('Walk-in'),
            // Where this bill's items came from — QR/Weigh/Advance order all
            // append to a table's one existing receipt now, so the "which
            // channel opened this" panel (Quotations' open-receipts picker)
            // has to derive it rather than read a single stored field.
            'channel_label' => $this->channelLabel(),
            'batch_count' => $this->items->pluck('batch_number')->filter()->unique()->count(),
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->item_name,
                'quantity' => $item->quantity,
                'subtotal' => (float) $item->subtotal,
                'is_weighed' => $item->isWeighed(),
                'detail' => $item->isWeighed() ? $item->weightLabel() : null,
                'cancelled' => $item->isFullyCancelled(),
            ])->values(),
            'subtotal' => (float) $totals->activeSubtotal,
            'total_amount' => (float) $totals->payableTotal(),
            'created_at' => $this->created_at?->toIso8601String(),
            'scheduled_for' => $this->sourceQuotation?->scheduled_for?->toIso8601String(),
        ];
    }

    /**
     * Best-guess origin for display only — nothing downstream depends on
     * this being exact. Checked in priority order: an advance order that
     * converted straight into a fresh order is unambiguous; a QR guest's
     * fingerprint (`ordered_by_guest_id`) and a weighed line both survive
     * even after later items land on the same bill from a different
     * channel, so whichever shows up first in the order's own item history
     * wins.
     */
    protected function channelLabel(): string
    {
        if ($this->sourceQuotation) {
            return __('Advance order');
        }

        if ($this->items->contains(fn ($item) => $item->ordered_by_guest_id !== null)) {
            return __('QR self-order');
        }

        if ($this->items->contains(fn ($item) => $item->isWeighed())) {
            return __('Weigh and Order');
        }

        return __('Staff');
    }
}
