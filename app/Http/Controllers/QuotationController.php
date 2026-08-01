<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Enums\SpaceStatus;
use App\Events\DashboardStatsChanged;
use App\Events\KitchenUpdated;
use App\Http\Requests\StoreQuotationRequest;
use App\Models\Area;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\Space;
use App\Services\OrderNumberGenerator;
use App\Services\TableSessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Advance orders / quotations pinned to a table ahead of time. Items
 * freeze their quoted name/price at quote time; converting appends them to
 * the table as a real Order exactly once, at the QUOTED prices — later
 * menu-price changes never reprice an existing quotation.
 *
 * A draft/sent quotation is a document, not an order: it never appears on
 * the kitchen board and never affects any amount due until converted.
 */
class QuotationController extends Controller
{
    public function index(): View
    {
        $quotations = Quotation::with(['space', 'area', 'creator', 'convertedOrder'])
            ->latest()
            ->get();

        return view('quotations.index', ['quotations' => $quotations]);
    }

    public function create(): View
    {
        return view('quotations.create', [
            'areas' => Area::where('is_active', true)
                ->with(['categories' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
                ->orderBy('sort_order')->orderBy('name')
                ->get()
                ->each(fn (Area $area) => $area->categories->each(
                    fn ($category) => $category->setRelation('spaces', $category->spaces()->orderBy('sort_order')->orderBy('name')->get())
                )),
            'categories' => MenuCategory::where('is_active', true)
                ->with(['menuItems' => fn ($query) => $query->with('variants')
                    ->whereIn('availability_status', ['available', 'seasonal'])
                    ->orderBy('sort_order')->orderBy('name')])
                ->orderBy('sort_order')->orderBy('name')
                ->get()
                ->filter(fn ($category) => $category->menuItems->isNotEmpty())
                ->values(),
        ]);
    }

    public function store(StoreQuotationRequest $request): RedirectResponse
    {
        $space = Space::findOrFail($request->integer('space_id'));

        $quotation = DB::transaction(function () use ($request, $space) {
            $lines = collect($request->input('items'))->map(function (array $line) {
                $menuItem = MenuItem::findOrFail($line['menu_item_id']);

                $variant = null;
                if ($menuItem->hasVariants()) {
                    $variant = ! empty($line['menu_item_variant_id'])
                        ? $menuItem->variants->firstWhere('id', (int) $line['menu_item_variant_id'])
                        : null;
                    $variant ??= $menuItem->variants->firstWhere('is_default', true) ?? $menuItem->variants->first();
                }

                $unitPrice = (string) ($variant->price ?? $menuItem->price);
                $quantity = (int) $line['quantity'];

                return [
                    'menu_item_id' => $menuItem->id,
                    'menu_item_variant_id' => $variant?->id,
                    'item_name' => $variant ? "{$menuItem->name} — {$variant->name}" : $menuItem->name,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'subtotal' => bcmul($unitPrice, (string) $quantity, 2),
                    'notes' => $line['notes'] ?? null,
                ];
            });

            $quotation = Quotation::create([
                // Placeholder until the row's own id exists — the public
                // number is derived from it, so it's unique without a
                // separate sequence table.
                'quotation_number' => 'TMP-'.Str::random(12),
                'area_id' => $space->area_id,
                'space_category_id' => $space->category_id,
                'space_id' => $space->id,
                'customer_name' => $request->string('customer_name')->toString() ?: null,
                'customer_contact' => $request->string('customer_contact')->toString() ?: null,
                'scheduled_for' => $request->date('scheduled_for'),
                'status' => QuotationStatus::Draft,
                'subtotal' => $lines->reduce(fn ($carry, $line) => bcadd($carry, $line['subtotal'], 2), '0.00'),
                'notes' => $request->string('notes')->toString() ?: null,
                'created_by' => auth()->id(),
            ]);

            $quotation->update(['quotation_number' => sprintf('QT-%05d', $quotation->id)]);
            $quotation->items()->createMany($lines->all());

            return $quotation;
        });

        return redirect()->route('quotations.show', $quotation)
            ->with('status', __('Quotation :number created.', ['number' => $quotation->quotation_number]));
    }

    public function show(Quotation $quotation): View
    {
        $quotation->load(['items', 'space', 'area', 'spaceCategory', 'creator', 'convertedOrder']);

        return view('quotations.show', ['quotation' => $quotation]);
    }

    /**
     * Walk the quotation through its lifecycle (sent → accepted →
     * confirmed, or cancelled). Converted/cancelled are terminal; the
     * conversion itself has its own dedicated action below.
     */
    public function updateStatus(Request $request, Quotation $quotation): RedirectResponse
    {
        $request->validate([
            'status' => ['required', Rule::in(['sent', 'accepted', 'confirmed', 'cancelled'])],
        ]);

        if (! $quotation->status->isOpen()) {
            return redirect()->back()->with('error', __('Quotation :number is already :status.', [
                'number' => $quotation->quotation_number,
                'status' => $quotation->status->label(),
            ]));
        }

        $target = QuotationStatus::from($request->string('status')->toString());

        $quotation->update([
            'status' => $target,
            'accepted_at' => $target === QuotationStatus::Accepted ? now() : $quotation->accepted_at,
            'confirmed_at' => $target === QuotationStatus::Confirmed ? now() : $quotation->confirmed_at,
            'cancelled_at' => $target === QuotationStatus::Cancelled ? now() : $quotation->cancelled_at,
        ]);

        return redirect()->back()->with('status', __('Quotation :number is now :status.', [
            'number' => $quotation->quotation_number,
            'status' => $target->label(),
        ]));
    }

    /**
     * Convert the accepted/confirmed quotation into a real Order on its
     * table — exactly once (row lock + converted_order_id guard), at the
     * frozen quoted prices, joined onto the table's active QR dining
     * session when one exists so it shows up in the combined table view.
     */
    public function convert(Quotation $quotation): RedirectResponse
    {
        try {
            $order = DB::transaction(function () use ($quotation) {
                $locked = Quotation::lockForUpdate()->findOrFail($quotation->id);

                if ($locked->converted_order_id !== null || ! in_array($locked->status, [QuotationStatus::Accepted, QuotationStatus::Confirmed], true)) {
                    throw ValidationException::withMessages([
                        'status' => __('Only an accepted/confirmed quotation that has not been converted yet can be converted.'),
                    ]);
                }

                $space = $locked->space;
                $session = $space ? TableSessionManager::activeSessionFor($space) : null;

                $order = Order::create([
                    'order_number' => OrderNumberGenerator::generate(),
                    'order_type' => OrderType::DineIn,
                    'area_id' => $locked->area_id,
                    'space_category_id' => $locked->space_category_id,
                    'space_id' => $locked->space_id,
                    'space_session_id' => $session?->id,
                    'batch_number' => $session?->nextBatchNumber(),
                    'created_by' => auth()->id(),
                    'status' => OrderStatus::Pending,
                    'payment_status' => PaymentStatus::Unpaid,
                    'total_amount' => $locked->subtotal,
                    'customer_name' => $locked->customer_name,
                    'notes' => trim(__('ADVANCE ORDER / QUOTATION').' '.$locked->quotation_number.($locked->notes ? ' — '.$locked->notes : '')),
                ]);

                $order->items()->createMany($locked->items->map(fn ($item) => [
                    'menu_item_id' => $item->menu_item_id,
                    'menu_item_variant_id' => $item->menu_item_variant_id,
                    'item_name' => $item->item_name,
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->subtotal,
                    'notes' => $item->notes,
                ])->all());

                $locked->update([
                    'status' => QuotationStatus::Converted,
                    'converted_at' => now(),
                    'converted_order_id' => $order->id,
                ]);

                if ($space && $space->status === SpaceStatus::Available) {
                    $space->setStatusWithSharedTables(SpaceStatus::Occupied);
                }

                return $order;
            });
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first());
        }

        broadcast(new KitchenUpdated());
        broadcast(new DashboardStatsChanged());

        return redirect()->route('orders.show', $order)
            ->with('status', __('Quotation :number converted to order :order.', [
                'number' => $quotation->quotation_number,
                'order' => $order->orderNumber(),
            ]));
    }
}
