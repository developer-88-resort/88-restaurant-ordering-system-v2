<?php

namespace App\Http\Controllers\Massage;

use App\Enums\CardBrand;
use App\Enums\MassageOrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\MassageOrder;
use App\Models\MassageService;
use App\Services\MassageCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Massage orders: pick the services, note the room (or guest), then take
 * payment the same way the restaurant does. Paid is the end of an order.
 */
class OrderController extends Controller
{
    /**
     * The restaurant's Order Management list (orders/index), for massage
     * orders: status buttons, Room / Walk-in filter and search, all in the
     * browser over the orders on the page (resources/js/lib/orders-browser.js).
     */
    public function index(): View
    {
        $orders = MassageOrder::with(['items', 'creator', 'recordedPayments'])->latest('id')->get();

        $orderIndex = $orders->mapWithKeys(function (MassageOrder $order) {
            $total = (float) $order->total_amount;
            $fields = array_values(array_filter([
                $order->order_number,
                $order->room_number,
                $order->room_number ? __('Room :number', ['number' => $order->room_number]) : null,
                $order->guest_name,
                $order->creator?->name,
                number_format($total, 2, '.', ''),
                number_format($total, 2),
                (string) (int) round($total),
            ], fn ($value) => $value !== null && $value !== ''));
            $fields = array_merge($fields, $order->items->map->label()->all());

            return [$order->id => [
                'status' => $order->status->value,
                'area' => $order->room_number ? 'room' : 'walkin',
                'fields' => array_values(array_unique(array_map('mb_strtolower', $fields))),
                'text' => mb_strtolower(implode(' ', array_merge($fields, [$order->status->label()]))),
                'created' => $order->created_at->getTimestamp(),
                'done' => ($order->paid_at ?? $order->cancelled_at ?? $order->created_at)->getTimestamp(),
            ]];
        });

        return view('massage.orders.index', [
            'orders' => $orders,
            'orderIndex' => $orderIndex,
            'totalOrders' => $orders->count(),
            'statusCounts' => $orders->countBy(fn (MassageOrder $order) => $order->status->value),
        ]);
    }

    public function create(): View
    {
        return view('massage.orders.create', [
            'services' => MassageService::available()->with(['images', 'variants', 'addOns'])->ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'room_number' => ['nullable', 'string', 'max:50', 'required_without:guest_name'],
            'guest_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_id' => ['required', 'integer', Rule::exists('massage_services', 'id')->whereNull('deleted_at')->where('is_available', true)],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.add_ons' => ['nullable', 'array'],
            'items.*.add_ons.*.id' => ['required', 'integer'],
            'items.*.add_ons.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ], [
            'room_number.required_without' => __('Enter the room number, or the guest name for a walk-in.'),
            'items.required' => __('Pick at least one massage service.'),
            'items.*.service_id.exists' => __('One of the massage services is no longer available.'),
        ]);

        // Prices come from the service, its variant and its add-ons — never
        // from the form. A service sold in variants has to be ordered as one
        // of them, and an add-on has to belong to the service it is on.
        $services = MassageService::with(['variants', 'addOns'])->whereIn('id', collect($validated['items'])->pluck('service_id'))->get()->keyBy('id');
        $lines = collect($validated['items'])->map(function ($row, $index) use ($services) {
            $service = $services[$row['service_id']];
            $variant = null;

            if ($service->orderableVariants()->isNotEmpty()) {
                $variant = $service->orderableVariants()->firstWhere('id', (int) ($row['variant_id'] ?? 0));
                if (! $variant) {
                    throw ValidationException::withMessages(["items.{$index}.variant_id" => __('Pick which :name to order.', ['name' => $service->name])]);
                }
            }

            $addOns = collect($row['add_ons'] ?? [])->map(function ($addOnRow) use ($service, $index) {
                $addOn = $service->addOns->firstWhere('id', (int) $addOnRow['id']);
                if (! $addOn) {
                    throw ValidationException::withMessages(["items.{$index}.add_ons" => __('One of the add-ons is no longer offered with :name.', ['name' => $service->name])]);
                }

                return ['add_on' => $addOn, 'per_massage' => (int) $addOnRow['quantity']];
            })->sortBy(fn ($a) => $a['add_on']->id)->values();

            return ['service' => $service, 'variant' => $variant, 'add_ons' => $addOns, 'quantity' => (int) $row['quantity']];
        })
            // The same massage, variant and add-ons twice is one line.
            ->groupBy(fn ($line) => $line['service']->id.'|'.($line['variant']?->id ?? 0).'|'.$line['add_ons']->map(fn ($a) => $a['add_on']->id.'x'.$a['per_massage'])->implode(','))
            ->map(function ($rows) {
                $line = $rows->first();
                $quantity = (int) $rows->sum('quantity');
                $unitPrice = (string) ($line['variant']?->price ?? $line['service']->price);
                $addOns = $line['add_ons']->map(function ($a) use ($quantity) {
                    $addOnQuantity = $a['per_massage'] * $quantity;

                    return [
                        'massage_service_add_on_id' => $a['add_on']->id,
                        'name' => $a['add_on']->name,
                        'unit_price' => $a['add_on']->price,
                        'quantity' => $addOnQuantity,
                        'subtotal' => bcmul((string) $a['add_on']->price, (string) $addOnQuantity, 2),
                    ];
                });

                return [
                    'item' => [
                        'massage_service_id' => $line['service']->id,
                        'massage_service_variant_id' => $line['variant']?->id,
                        'name' => $line['service']->name,
                        'variant_name' => $line['variant']?->name,
                        'unit_price' => $unitPrice,
                        'quantity' => $quantity,
                        'subtotal' => $addOns->reduce(fn ($sum, $a) => bcadd($sum, $a['subtotal'], 2), bcmul($unitPrice, (string) $quantity, 2)),
                    ],
                    'add_ons' => $addOns,
                ];
            })
            ->values();

        $order = DB::transaction(function () use ($validated, $request, $lines) {
            $order = MassageOrder::create([
                'order_number' => MassageOrder::nextOrderNumber(),
                'room_number' => $validated['room_number'] ?? null,
                'guest_name' => $validated['guest_name'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => MassageOrderStatus::Pending,
                'total_amount' => $lines->reduce(fn ($sum, $line) => bcadd($sum, $line['item']['subtotal'], 2), '0.00'),
                'created_by' => $request->user()->id,
            ]);

            foreach ($lines as $line) {
                $order->items()->create($line['item'])->addOns()->createMany($line['add_ons']->all());
            }

            return $order;
        });

        return redirect()->route('massage.orders.show', $order)
            ->with('status', __('Massage order :number created. Take payment when the guest is ready.', ['number' => $order->order_number]));
    }

    public function show(MassageOrder $order): View
    {
        $order->load(['items.addOns', 'creator', 'payments.receivedBy']);

        return view('massage.orders.show', [
            'order' => $order,
            'paymentConfig' => $this->paymentConfig($order),
        ]);
    }

    public function pay(Request $request, MassageOrder $order): RedirectResponse
    {
        $request->validate([
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required', 'numeric', 'min:0'],
            'payments.*.tendered_amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.card_brand' => ['nullable', Rule::enum(CardBrand::class)],
            'payments.*.settled_via' => ['nullable', Rule::enum(PaymentMethod::class)],
            'payments.*.charged_to' => ['nullable', 'string', 'max:255'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
            'payments.*.approval_code' => ['nullable', 'string', 'max:255'],
            'payments.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        MassageCheckout::pay($order, $request->input('payments'), $request->user());

        return redirect()->route('massage.orders.show', $order)
            ->with('status', __('Massage order :number is paid.', ['number' => $order->order_number]));
    }

    public function cancel(MassageOrder $order): RedirectResponse
    {
        if (! $order->isOpen()) {
            return back()->with('error', __('Only an unpaid massage order can be cancelled.'));
        }

        $order->update(['status' => MassageOrderStatus::Cancelled, 'cancelled_at' => now()]);

        return redirect()->route('massage.orders.show', $order)
            ->with('status', __('Massage order :number was cancelled.', ['number' => $order->order_number]));
    }

    public function voidPayment(Request $request, MassageOrder $order): RedirectResponse
    {
        if ($order->status !== MassageOrderStatus::Paid) {
            return back()->with('error', __('Only a paid massage order has a payment to void.'));
        }

        MassageCheckout::voidPayment($order, $request->user());

        return redirect()->route('massage.orders.show', $order)
            ->with('status', __('The payment for :number was voided. The order is unpaid again.', ['number' => $order->order_number]));
    }

    /**
     * What the restaurant checkout's orderPayment() component needs, minus
     * discounts, VAT and service charge (Massage has none of those).
     *
     * @return array<string, mixed>
     */
    protected function paymentConfig(MassageOrder $order): array
    {
        return [
            'orderTotal' => (float) $order->total_amount,
            'isVat' => false,
            'taxRate' => 0,
            'serviceChargeEnabled' => false,
            'serviceChargePercent' => 0,
            'isStaff' => false,
            'rules' => [],
            'orderItems' => [],
            'methods' => collect(PaymentMethod::cases())->map(fn ($method) => [
                'value' => $method->value,
                'label' => $method->label(),
                'requiresReference' => $method->requiresReference(),
            ])->values(),
            'settlementMethods' => collect(PaymentMethod::settlementOptions())->map(fn ($method) => [
                'value' => $method->value,
                'label' => $method->label(),
            ])->values(),
            'cardBrands' => collect(CardBrand::cases())->map(fn ($brand) => [
                'value' => $brand->value,
                'label' => $brand->label(),
            ])->values(),
            'lateDiscount' => false,
            'paidTotal' => null,
            'initialSelections' => (object) [],
        ];
    }
}
