<?php

namespace App\Http\Controllers;

use App\Enums\OrderType;
use App\Enums\PricingType;
use App\Models\Area;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Space;
use App\Models\SpaceSession;
use App\Services\OrderAppender;
use App\Services\OrderNumberGenerator;
use App\Services\TableSessionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The weigh station: the tablet screen at the counter where staff put fish
 * or meat on the scale and turn it into a line on the customer's running
 * bill.
 *
 * The wizard resolves WHICH order the line belongs to before it is
 * recorded (step 5), then hands the line to the same
 * POST /orders/{order}/items endpoint everything else appends through — so
 * a weighed line lands on the table's existing bill instead of opening a
 * second order.
 */
class WeighStationController extends Controller
{
    public function index(Request $request): Response
    {
        $setting = Setting::current();

        return Inertia::render('Weigh/Station', [
            'categories' => $this->perKiloItemsByCategory(),
            'areas' => $this->areasWithTables(),
            'can' => [
                'overridePrice' => $request->user()->can('weigh.override_price'),
            ],
            // Off by default — at the counter the customer is standing there
            // watching the scale, so the extra confirmation step is opt-in.
            'requiresCustomerConfirmation' => (bool) $setting->weigh_customer_confirmation_enabled,
        ]);
    }

    /**
     * Step 5's lookup: who is at this table right now, and what is already
     * on their bill. Returns `session: null` when the table has nobody
     * seated, which is what drives the "no open session" branch in the UI.
     */
    public function tableSession(Space $space): JsonResponse
    {
        $session = TableSessionManager::activeSessionFor($space);

        if (! $session) {
            return response()->json([
                'session' => null,
                'space' => ['id' => $space->id, 'name' => $space->name],
            ]);
        }

        $order = OrderAppender::findOpenOrderForSession($session);

        return response()->json([
            'space' => ['id' => $space->id, 'name' => $space->name],
            'session' => [
                'id' => $session->id,
                'started_at' => $session->started_at?->format('g:i A'),
                'guests' => $session->guestSessions()
                    ->where('status', 'active')
                    ->orderBy('guest_number')
                    ->get()
                    ->map(fn ($guest) => [
                        'id' => $guest->id,
                        'number' => $guest->guest_number,
                        // "Guest 1 — Test Guest" when they typed a name on
                        // the welcome screen, otherwise just "Guest 1".
                        'label' => $guest->display_name
                            ? __('Guest :number', ['number' => $guest->guest_number]).' — '.$guest->display_name
                            : __('Guest :number', ['number' => $guest->guest_number]),
                    ])->values(),
            ],
            'order' => $order ? $this->orderPreview($order) : null,
        ]);
    }

    /**
     * "Open a session" — the table has nobody seated but a customer is at
     * the counter with fish. Seats them and starts their bill in one go.
     */
    public function openSession(Space $space): JsonResponse
    {
        $session = TableSessionManager::findOrOpenFor($space);
        $order = OrderAppender::findOrStartOrderForSession($session, ['created_by' => auth()->id()]);

        return response()->json([
            'session' => ['id' => $session->id],
            'order' => $this->orderPreview($order),
        ]);
    }

    /**
     * "Create a walk-in order" — a take-out customer, or someone not seated
     * at any table. No session and no table; just a bill with a name on it.
     */
    public function walkInOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
        ]);

        $order = DB::transaction(fn () => Order::create([
            'order_type' => OrderType::Takeout,
            'order_number' => OrderNumberGenerator::generate(),
            'status' => \App\Enums\OrderStatus::Pending,
            'payment_status' => \App\Enums\PaymentStatus::Unpaid,
            'total_amount' => '0.00',
            'created_by' => $request->user()->id,
            'customer_name' => $data['customer_name'] ?? null,
            'notes' => ! empty($data['contact_number'])
                ? __('Contact: :number', ['number' => $data['contact_number']])
                : null,
        ]));

        return response()->json(['order' => $this->orderPreview($order)]);
    }

    /**
     * Per-kilo items grouped by menu category, each carrying TODAY's rate —
     * the market price when one is set for the day, otherwise the item's
     * standing rate. This is what step 1 shows on each card and what step 2
     * pre-fills.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function perKiloItemsByCategory(): array
    {
        return MenuCategory::where('is_active', true)
            ->with(['menuItems' => fn ($query) => $query
                ->where('pricing_type', PricingType::PerKilo)
                ->whereIn('availability_status', ['available', 'seasonal'])
                ->with(['images', 'cookingStyles' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('sort_order')
                ->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (MenuCategory $category) => $category->menuItems->isNotEmpty())
            ->map(fn (MenuCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'items' => $category->menuItems->map(fn (MenuItem $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'image_url' => $item->primaryImageUrl(),
                    'price_per_kilo' => (float) $item->effectivePricePerKilo(),
                    'default_price_per_kilo' => (float) $item->price_per_kilo,
                    'min_weight_grams' => (int) $item->min_weight_grams,
                    'weight_step_grams' => (int) $item->weight_step_grams,
                    'allow_tare' => (bool) $item->allow_tare,
                    'cooking_styles' => $item->cookingStyles->map(fn ($style) => [
                        'id' => $style->id,
                        'name' => $style->name,
                        'surcharge' => (float) $style->surcharge,
                    ])->values(),
                ])->values(),
            ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function areasWithTables(): array
    {
        return Area::where('is_active', true)
            ->with(['spaces' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Area $area) => $area->spaces->isNotEmpty())
            ->map(fn (Area $area) => [
                'id' => $area->id,
                'name' => $area->name,
                'tables' => $area->spaces->map(fn (Space $space) => [
                    'id' => $space->id,
                    'name' => $space->name,
                    'status' => $space->status->value,
                ])->values(),
            ])->values()->all();
    }

    /**
     * What is already on this bill — shown in step 5 so staff can see they
     * are adding to the right party's order before they commit.
     *
     * @return array<string, mixed>
     */
    protected function orderPreview(Order $order): array
    {
        $order->loadMissing(['items.adjustments', 'items.cookingStyle']);

        return [
            'id' => $order->id,
            'order_number' => $order->orderNumber(),
            'total_amount' => (float) $order->total_amount,
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->item_name,
                'quantity' => $item->quantity,
                'subtotal' => (float) $item->subtotal,
                'is_weighed' => $item->isWeighed(),
                'detail' => $item->isWeighed() ? $item->weightLabel() : null,
                'cancelled' => $item->isFullyCancelled(),
            ])->values(),
        ];
    }
}
