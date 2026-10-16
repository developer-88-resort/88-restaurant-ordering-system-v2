<?php

namespace App\Http\Controllers;

use App\Enums\OrderItemAdjustmentSource;
use App\Enums\OrderStatus;
use App\Http\Requests\CancelOrderItemRequest;
use App\Models\DiscountRule;
use App\Models\MediaEvidence;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderItemCanceller;
use App\Services\Printing\KitchenSlipQueue;
use App\Support\ManagerApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KitchenController extends Controller
{
    public function index(Request $request): View
    {
        $eagerLoads = ['area', 'spaceCategory', 'space', 'guestSession', 'items.adjustments', 'items.cookingStyle', 'items.menuItem', 'items.quotation', 'sourceQuotation'];

        // Advance orders land straight in the same lanes as everything
        // else the moment they're converted — no separate "not cookable
        // yet" holding area. `order-card` flags them with an "Advance
        // order" badge instead, since a whole extra lane just to say
        // "this one's from a quotation" was confusing staff more than it
        // helped.
        // Newest first: a lane can hold more orders than fit on screen, and
        // with the oldest on top a just-placed order landed off the bottom
        // where nobody saw it come in. The kitchen needs to notice arrivals,
        // so the newest sits where the eye already is.
        $orders = Order::with($eagerLoads)
            ->whereIn('status', [OrderStatus::Pending, OrderStatus::Preparing, OrderStatus::Ready])
            ->latest()
            ->get()
            ->groupBy(fn (Order $order) => $order->status->value);

        // Several slips can be open for one table at once; each card names
        // the others so the kitchen can plate a table's slips together.
        $slipsByTab = $orders->flatten()
            ->filter(fn (Order $order) => $order->space_session_id !== null)
            ->groupBy('space_session_id');

        $newCount = $orders->flatten()
            ->filter(fn (Order $order) => $order->created_at->diffInMinutes(now()) < 2)
            ->count();

        return view('kitchen.index', [
            'pending' => $orders->get(OrderStatus::Pending->value, collect()),
            'preparing' => $orders->get(OrderStatus::Preparing->value, collect()),
            'ready' => $orders->get(OrderStatus::Ready->value, collect()),
            'newCount' => $newCount,
            'slipsByTab' => $slipsByTab,
            // Whether this user needs a manager's sign-off is the same
            // for every line on a slip, so it's worked out once per card
            // rather than once per line.
            'isManager' => $request->user()->isManager(),
            // Who a staff member can pick to approve with a PIN.
            'approvers' => $request->user()->isManager() ? [] : ManagerApproval::pinApprovers(),
            // Slips already waiting or on the printer, so Direct Print stays
            // locked on those cards even after the board refreshes itself.
            'activePrintJobs' => KitchenSlipQueue::activeJobsFor($orders->flatten()->pluck('id')->all()),
            // The same rules checkout offers, for the slip's own discount —
            // except headcount-based ones (Diplomat): they only strip VAT,
            // which the slip's plain math doesn't model, so they'd show ₱0.
            'slipDiscountRules' => DiscountRule::currentlyAvailable()->where('scope', '!=', 'per_person')->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    /**
     * Cancel all or part of one line from the Kitchen Display. Answers in
     * JSON so the dialog can stay open on a wrong manager password or an
     * "Other" with no explanation, instead of reloading the whole board.
     */
    public function cancelItem(CancelOrderItemRequest $request, Order $order, OrderItem $orderItem): JsonResponse
    {
        abort_unless($orderItem->order_id === $order->id, 404);

        Gate::authorize('cancel', $orderItem);

        $adjustment = OrderItemCanceller::cancel(
            $order,
            $orderItem,
            $request->validated(),
            $request->user(),
            OrderItemAdjustmentSource::Kitchen,
        );

        $order->refresh();

        return response()->json([
            'message' => $order->status === OrderStatus::Cancelled
                ? __('Every item is cancelled — order :number is now cancelled.', ['number' => $order->orderNumber()])
                : __(':qty× :item cancelled.', ['qty' => $adjustment->quantity, 'item' => $orderItem->item_name]),
            'order_status' => $order->status->value,
            'total_amount' => (string) $order->total_amount,
        ]);
    }

    /**
     * Serve one evidence file. Files live on the private local disk —
     * this authenticated, role-gated route is the only way to reach them.
     */
    public function showEvidence(MediaEvidence $mediaEvidence): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($mediaEvidence->path), 404);

        return Storage::disk('local')->response($mediaEvidence->path, $mediaEvidence->original_name);
    }
}
