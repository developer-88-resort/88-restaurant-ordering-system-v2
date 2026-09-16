<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\MediaEvidence;
use App\Models\Order;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KitchenController extends Controller
{
    public function index(): View
    {
        $eagerLoads = ['area', 'spaceCategory', 'space', 'guestSession', 'items.adjustments', 'items.cookingStyle', 'items.menuItem', 'sourceQuotation'];

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

        $newCount = $orders->flatten()
            ->filter(fn (Order $order) => $order->created_at->diffInMinutes(now()) < 2)
            ->count();

        return view('kitchen.index', [
            'pending' => $orders->get(OrderStatus::Pending->value, collect()),
            'preparing' => $orders->get(OrderStatus::Preparing->value, collect()),
            'ready' => $orders->get(OrderStatus::Ready->value, collect()),
            'newCount' => $newCount,
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
