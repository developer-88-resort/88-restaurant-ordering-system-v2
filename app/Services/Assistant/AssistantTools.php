<?php

namespace App\Services\Assistant;

use App\Enums\Department;
use App\Enums\MassageOrderStatus;
use App\Enums\MenuItemAvailability;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PricingType;
use App\Enums\QuotationStatus;
use App\Enums\SpaceStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Superadmin\ReportController;
use App\Models\DailyMarketPrice;
use App\Models\MassageOrder;
use App\Models\MassagePayment;
use App\Models\MassageService;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\Space;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What the assistant (Mochi, the floating chat of a Superadmin or Admin)
 * can look up and do, as Gemini function declarations plus their
 * server-side handlers. allowedFor() limits an Admin to what their own
 * pages show: Reports (sales, best sellers, Massage totals) plus their
 * department's pages; accounts and system status stay Superadmin-only.
 *
 * Look-ups run straight away and only read. Actions never run from here:
 * they come back as a proposal the user confirms in the chat
 * (AssistantController::confirm), and run as that user, so the usual
 * audit log records them.
 */
class AssistantTools
{
    /** Actions — proposed by the model, run only after the user confirms. */
    public const ACTIONS = ['set_menu_item_availability', 'set_massage_service_availability'];

    /** The tools this user may use (by name). */
    public static function allowedFor(User $user): array
    {
        if ($user->role === UserRole::Superadmin) {
            return array_column(self::declarations(), 'name');
        }

        // Reports is open to every Admin and shows these for both departments.
        $tools = ['sales_summary', 'sales_compare', 'best_sellers', 'massage_summary', 'account_charges', 'open_page'];
        if ($user->worksIn(Department::Restaurant)) {
            array_push($tools, 'list_orders', 'get_order', 'menu_items', 'spaces_status', 'quotations', 'kitchen_queue', 'market_prices', 'set_menu_item_availability');
        }
        if ($user->worksIn(Department::Services)) {
            $tools[] = 'set_massage_service_availability';
        }

        return $tools;
    }

    /** The declarations of the tools this user may use. */
    public static function declarationsFor(User $user): array
    {
        $allowed = self::allowedFor($user);

        return array_values(array_filter(self::declarations($user), fn ($tool) => in_array($tool['name'], $allowed, true)));
    }

    /**
     * The pages open_page can give a button to, by key, for this user only
     * (the same department / Superadmin rules as the sidebar).
     *
     * @return array<string, array{0: string, 1: string}> key => [label, route name]
     */
    public static function pagesFor(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $superadmin = $user->role === UserRole::Superadmin;
        $restaurant = $user->worksIn(Department::Restaurant);
        $services = $user->worksIn(Department::Services);

        return array_filter([
            'overview' => $restaurant ? ['Overview', 'superadmin.dashboard'] : null,
            'order_management' => $restaurant ? ['Order Management', 'orders.index'] : null,
            'new_order' => $restaurant ? ['New Order', 'orders.create'] : null,
            'kitchen' => $restaurant ? ['Kitchen', 'kitchen.index'] : null,
            'quotations' => $restaurant ? ['Quotations', 'quotations.index'] : null,
            'spaces' => $restaurant ? ['Spaces', 'spaces.index'] : null,
            'menu_management' => $restaurant ? ['Menu Management', 'menu-items.index'] : null,
            'market_prices' => $restaurant ? ['Daily Market Prices', 'weigh.prices.index'] : null,
            'promotions' => $restaurant ? ['Promotions & Banners', 'superadmin.promotions.index'] : null,
            'reports' => ['Reports', 'superadmin.reports.index'],
            'chat' => ['Chat', 'chat.index'],
            'massage_overview' => $services ? ['Massage Overview', 'massage.dashboard'] : null,
            'massage_orders' => $services ? ['Massage Orders', 'massage.orders.index'] : null,
            'massage_services' => $services ? ['Massage Services', 'massage.services.index'] : null,
            'user_management' => $superadmin ? ['User Management', 'superadmin.users.index'] : null,
            'audit_logs' => $superadmin ? ['Audit Logs', 'superadmin.audit-logs.index'] : null,
            'settings' => $superadmin ? ['Settings', 'superadmin.settings.edit'] : null,
            'rooms' => $superadmin ? ['Rooms', 'superadmin.rooms.index'] : null,
        ]);
    }

    public static function declarations(?User $user = null): array
    {
        $period = [
            'type' => 'STRING',
            'description' => 'today, yesterday, tomorrow, this_week, last_week, next_7_days, this_month, last_month, before_today (every day before today), or a date YYYY-MM-DD. Default today.',
        ];
        $pages = array_keys(self::pagesFor($user ?? auth()->user())) ?: ['reports'];

        return [
            ['name' => 'sales_summary', 'description' => 'Restaurant sales for a period: paid orders and revenue (as on Reports), money collected by payment method, open/unpaid and cancelled orders, plus the Massage department total.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['period' => $period]]],
            ['name' => 'list_orders', 'description' => 'Restaurant orders (slips), newest first, at most 20. Filter by status, payment, period or a search word (order number, table, customer, staff).', 'parameters' => ['type' => 'OBJECT', 'properties' => [
                'status' => ['type' => 'STRING', 'description' => 'pending, preparing, ready, served, completed, cancelled, open (not completed or cancelled)'],
                'payment' => ['type' => 'STRING', 'description' => 'unpaid, paid or voided. For "unpaid orders" use payment=unpaid with NO status and NO period: an unpaid order can be of any day and even marked Completed.'],
                'period' => $period,
                'search' => ['type' => 'STRING'],
                'limit' => ['type' => 'INTEGER'],
            ]]],
            ['name' => 'get_order', 'description' => 'One restaurant order in full: items, payments, status, table, who made it.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['order_number' => ['type' => 'STRING', 'description' => 'As shown, e.g. ORD-1007-012']], 'required' => ['order_number']]],
            ['name' => 'best_sellers', 'description' => 'Best-selling menu items by quantity and revenue for a period (paid orders).', 'parameters' => ['type' => 'OBJECT', 'properties' => ['period' => $period, 'limit' => ['type' => 'INTEGER']]]],
            ['name' => 'menu_items', 'description' => 'Menu items with category, price and availability. Search by name and/or category, or filter by availability.', 'parameters' => ['type' => 'OBJECT', 'properties' => [
                'search' => ['type' => 'STRING'],
                'category' => ['type' => 'STRING'],
                'availability' => ['type' => 'STRING', 'description' => 'available, out_of_stock, seasonal, hidden'],
            ]]],
            ['name' => 'spaces_status', 'description' => 'Tables/spaces: how many are available, occupied, reserved, under maintenance, and which are occupied.', 'parameters' => ['type' => 'OBJECT', 'properties' => (object) []]],
            ['name' => 'massage_summary', 'description' => 'Massage department for a period: paid orders and revenue, unpaid, services sold, and its services with prices and availability.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['period' => $period]]],
            ['name' => 'sales_compare', 'description' => 'Compare sales of two periods (e.g. today vs yesterday, this_week vs last_week): restaurant and Massage revenue and paid orders, with the change in pesos and percent.', 'parameters' => ['type' => 'OBJECT', 'properties' => [
                'period' => $period,
                'compare_to' => $period,
            ], 'required' => ['period', 'compare_to']]],
            ['name' => 'quotations', 'description' => 'Advance orders (Quotations) scheduled in a period, default next_7_days: number, customer, when, table, amount, status. Not cancelled ones unless asked.', 'parameters' => ['type' => 'OBJECT', 'properties' => [
                'period' => $period,
                'include_cancelled' => ['type' => 'BOOLEAN'],
            ]]],
            ['name' => 'kitchen_queue', 'description' => 'Slips on the Kitchen display right now (New/Pending, Preparing, Ready) with how many minutes each has waited, oldest first; marks those waiting over 20 minutes.', 'parameters' => ['type' => 'OBJECT', 'properties' => (object) []]],
            ['name' => 'market_prices', 'description' => 'Per-kilo (weighed) items and their Daily Market Price for a day (default today): which are set, which are not yet set (those fall back to the standing rate).', 'parameters' => ['type' => 'OBJECT', 'properties' => ['date' => ['type' => 'STRING', 'description' => 'YYYY-MM-DD, default today']]]],
            ['name' => 'account_charges', 'description' => 'Room, Locker and Kubo charges (restaurant and Massage) for a period: where charged, guest, amount, order, and how it is settled. Room/locker/kubo charges are settled by the guest at front desk checkout; the system does not record whether the front desk has collected them.', 'parameters' => ['type' => 'OBJECT', 'properties' => [
                'period' => $period,
                'type' => ['type' => 'STRING', 'description' => 'room, locker, kubo or all (default all)'],
            ]]],
            ['name' => 'open_page', 'description' => 'Gives the user a button that opens a page of the system. Call it whenever you explain how to do something or where something is.', 'parameters' => ['type' => 'OBJECT', 'properties' => [
                'page' => ['type' => 'STRING', 'enum' => $pages],
            ], 'required' => ['page']]],
            ['name' => 'users_list', 'description' => 'Accounts: name, role, department and whether active. No passwords or PINs.', 'parameters' => ['type' => 'OBJECT', 'properties' => (object) []]],
            ['name' => 'system_status', 'description' => 'Whether Maintenance Mode is on, and counts of menu items, orders and users.', 'parameters' => ['type' => 'OBJECT', 'properties' => (object) []]],
            ['name' => 'set_menu_item_availability', 'description' => 'ACTION (needs the user to confirm): change a menu item\'s availability.', 'parameters' => ['type' => 'OBJECT', 'properties' => [
                'item_name' => ['type' => 'STRING'],
                'availability' => ['type' => 'STRING', 'description' => 'available, out_of_stock, seasonal or hidden'],
            ], 'required' => ['item_name', 'availability']]],
            ['name' => 'set_massage_service_availability', 'description' => 'ACTION (needs the user to confirm): make a massage service available or unavailable.', 'parameters' => ['type' => 'OBJECT', 'properties' => [
                'service_name' => ['type' => 'STRING'],
                'available' => ['type' => 'BOOLEAN'],
            ], 'required' => ['service_name', 'available']]],
        ];
    }

    /**
     * Runs a look-up and returns what the model reads back. `_links`
     * (buttons under the answer: [label, url]) is taken off by the
     * controller before the model sees the result.
     */
    public static function lookup(string $name, array $args): array
    {
        return match ($name) {
            'sales_summary' => self::salesSummary($args) + ['_links' => [self::pageLink('reports')]],
            'sales_compare' => self::salesCompare($args) + ['_links' => [self::pageLink('reports')]],
            'list_orders' => self::listOrders($args),
            'get_order' => self::getOrder($args),
            'best_sellers' => self::bestSellers($args) + ['_links' => [self::pageLink('reports')]],
            'menu_items' => self::menuItems($args),
            'spaces_status' => self::spacesStatus() + ['_links' => [self::pageLink('spaces')]],
            'massage_summary' => self::massageSummary($args) + ['_links' => [self::pageLink('massage_overview') ?? self::pageLink('reports')]],
            'quotations' => self::quotations($args),
            'kitchen_queue' => self::kitchenQueue() + ['_links' => [self::pageLink('kitchen')]],
            'market_prices' => self::marketPrices($args) + ['_links' => [self::pageLink('market_prices')]],
            'account_charges' => self::accountCharges($args) + ['_links' => [self::pageLink('reports')]],
            'open_page' => self::openPage($args),
            'users_list' => self::usersList() + ['_links' => [self::pageLink('user_management')]],
            'system_status' => self::systemStatus() + ['_links' => [self::pageLink('settings')]],
            default => ['error' => 'Unknown tool '.$name],
        };
    }

    /** A button to one of the user's pages (pagesFor), or null if they can't open it. */
    protected static function pageLink(string $key): ?array
    {
        $page = self::pagesFor(auth()->user())[$key] ?? null;

        return $page ? ['label' => __('Go to :page', ['page' => __($page[0])]), 'url' => route($page[1])] : null;
    }

    protected static function openPage(array $args): array
    {
        $link = self::pageLink((string) ($args['page'] ?? ''));

        return $link
            ? ['button_shown' => $link['label'], '_links' => [$link]]
            : ['error' => 'This user cannot open that page.'];
    }

    /**
     * Checks an action and describes it for the confirm card, without
     * running it. Returns ['error' => ...] when it can't be done.
     */
    public static function prepare(string $name, array $args): array
    {
        if ($name === 'set_menu_item_availability') {
            $availability = MenuItemAvailability::tryFrom((string) ($args['availability'] ?? ''));
            $item = self::findByName(MenuItem::query(), (string) ($args['item_name'] ?? ''));
            if (! $availability) {
                return ['error' => 'Availability must be available, out_of_stock, seasonal or hidden.'];
            }
            if (! $item) {
                return ['error' => 'No menu item matches "'.($args['item_name'] ?? '').'".'];
            }

            return ['summary' => __('Set ":item" to :status (now :current).', ['item' => $item->name, 'status' => $availability->label(), 'current' => $item->availability_status->label()]), 'args' => ['id' => $item->id, 'availability' => $availability->value]];
        }

        if ($name === 'set_massage_service_availability') {
            $service = self::findByName(MassageService::query(), (string) ($args['service_name'] ?? ''));
            if (! $service) {
                return ['error' => 'No massage service matches "'.($args['service_name'] ?? '').'".'];
            }
            $available = (bool) ($args['available'] ?? false);

            return ['summary' => __('Make massage service ":service" :status.', ['service' => $service->name, 'status' => $available ? __('available') : __('unavailable')]), 'args' => ['id' => $service->id, 'available' => $available]];
        }

        return ['error' => 'Unknown action '.$name];
    }

    /** Runs a confirmed action (prepared args) and says what happened. */
    public static function perform(string $name, array $args): string
    {
        if ($name === 'set_menu_item_availability') {
            $item = MenuItem::findOrFail($args['id']);
            $item->update(['availability_status' => $args['availability']]);

            return __('Done: ":item" is now :status.', ['item' => $item->name, 'status' => $item->availability_status->label()]);
        }

        if ($name === 'set_massage_service_availability') {
            $service = MassageService::findOrFail($args['id']);
            $service->update(['is_available' => $args['available']]);

            return __('Done: massage service ":service" is now :status.', ['service' => $service->name, 'status' => $args['available'] ? __('available') : __('unavailable')]);
        }

        throw new \InvalidArgumentException('Unknown action '.$name);
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    public static function period(?string $period): array
    {
        $period = trim((string) $period) ?: 'today';

        return match ($period) {
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay(), 'yesterday'],
            'tomorrow' => [now()->addDay()->startOfDay(), now()->addDay()->endOfDay(), 'tomorrow'],
            'next_7_days' => [now()->startOfDay(), now()->addDays(6)->endOfDay(), 'the next 7 days'],
            'before_today' => [Carbon::create(2000), now()->subDay()->endOfDay(), 'before today'],
            'this_week' => [now()->startOfWeek(), now()->endOfWeek(), 'this week'],
            'last_week' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek(), 'last week'],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth(), 'this month'],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth(), 'last month'],
            default => preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)
                ? [Carbon::parse($period)->startOfDay(), Carbon::parse($period)->endOfDay(), $period]
                : [now()->startOfDay(), now()->endOfDay(), 'today'],
        };
    }

    protected static function money(float|string|null $amount): string
    {
        return '₱'.number_format((float) $amount, 2);
    }

    protected static function salesSummary(array $args): array
    {
        [$start, $end, $label] = self::period($args['period'] ?? null);
        $orders = ReportController::countableOrders()->whereBetween('created_at', [$start, $end]);
        $paid = (clone $orders)->where('payment_status', PaymentStatus::Paid);

        $byMethod = OrderPayment::where('status', OrderPaymentStatus::Recorded->value)
            ->whereBetween('received_at', [$start, $end])
            ->get()
            ->groupBy(fn (OrderPayment $payment) => $payment->payment_method->label())
            ->map(fn ($payments) => ['count' => $payments->count(), 'amount' => self::money($payments->sum('amount'))]);

        $massagePaid = MassageOrder::where('status', MassageOrderStatus::Paid)->whereBetween('paid_at', [$start, $end]);

        return [
            'period' => $label,
            'from' => $start->toDateTimeString(),
            'to' => $end->toDateTimeString(),
            'restaurant_paid_orders' => (clone $paid)->count(),
            'restaurant_revenue' => self::money((clone $paid)->sum('total_amount')),
            'restaurant_open_unpaid_orders' => (clone $orders)->where('status', '!=', 'cancelled')->where('payment_status', '!=', PaymentStatus::Paid)->count(),
            'restaurant_cancelled_orders' => (clone $orders)->where('status', 'cancelled')->count(),
            'collected_by_payment_method' => $byMethod,
            'massage_paid_orders' => (clone $massagePaid)->count(),
            'massage_revenue' => self::money((clone $massagePaid)->sum('total_amount')),
            'note' => 'Revenue = paid orders created in the period, gross before discounts (same as Reports).',
        ];
    }

    protected static function listOrders(array $args): array
    {
        $query = Order::with(['space', 'area', 'spaceCategory', 'creator'])->latest();

        $status = (string) ($args['status'] ?? '');
        if ($status === 'open') {
            $query->whereNotIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value]);
        } elseif (OrderStatus::tryFrom($status)) {
            $query->where('status', $status);
        }
        if (PaymentStatus::tryFrom((string) ($args['payment'] ?? ''))) {
            $query->where('payment_status', $args['payment']);
            // "Unpaid" means money still to collect, as Mochi's reminder
            // counts it: not a cancelled slip, nor one merged into another.
            if ($args['payment'] === PaymentStatus::Unpaid->value && $status !== OrderStatus::Cancelled->value) {
                $query->where('status', '!=', OrderStatus::Cancelled->value)->whereNull('merged_into_order_id');
            }
        }
        if (! empty($args['period'])) {
            [$start, $end] = self::period($args['period']);
            $query->whereBetween('created_at', [$start, $end]);
        }

        $orders = $query->limit(200)->get();
        if ($search = mb_strtolower(trim((string) ($args['search'] ?? '')))) {
            $orders = $orders->filter(fn (Order $order) => str_contains(mb_strtolower(implode(' ', [
                $order->orderNumber(), $order->locationLabel(), $order->customer_name, $order->creator?->name,
            ])), $search));
        }

        $limit = max(1, min(20, (int) ($args['limit'] ?? 10)));
        $shown = $orders->take($limit);

        return ['_links' => self::orderLinks($shown), 'orders' => $shown->map(fn (Order $order) => [
            'order_number' => $order->orderNumber(),
            'location' => $order->locationLabel(),
            'status' => $order->status->label(),
            'payment' => $order->payment_status->label(),
            'total' => self::money($order->total_amount),
            'created' => $order->created_at->format('M j, g:i A'),
            'by' => $order->creator?->name,
        ])->values(), 'matching_shown' => min($limit, $orders->count())];
    }

    protected static function getOrder(array $args): array
    {
        $wanted = mb_strtolower(trim((string) ($args['order_number'] ?? '')));
        $order = Order::with(['items', 'payments', 'space', 'area', 'spaceCategory', 'creator'])
            ->where('order_number', $args['order_number'] ?? '')
            ->first()
            ?? Order::with(['items', 'payments', 'space', 'area', 'spaceCategory', 'creator'])->latest()->limit(500)->get()
                ->first(fn (Order $order) => mb_strtolower($order->orderNumber()) === $wanted);

        if (! $order) {
            return ['error' => 'No order '.$args['order_number'].'.'];
        }

        return [
            '_links' => self::orderLinks(collect([$order])),
            'order_number' => $order->orderNumber(),
            'location' => $order->locationLabel(),
            'status' => $order->status->label(),
            'payment' => $order->payment_status->label(),
            'total' => self::money($order->total_amount),
            'created' => $order->created_at->format('M j, Y g:i A'),
            'by' => $order->creator?->name,
            'customer' => $order->customer_name,
            'items' => $order->items->map(fn ($item) => $item->quantity.' × '.$item->item_name.' = '.self::money($item->subtotal))->values(),
            'payments' => $order->payments->map(fn ($payment) => $payment->payment_method->label().' '.self::money($payment->amount).' ('.$payment->status->value.')')->values(),
        ];
    }

    protected static function bestSellers(array $args): array
    {
        [$start, $end, $label] = self::period($args['period'] ?? 'this_week');

        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.merged_into_order_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->whereBetween('orders.created_at', [$start, $end])
            ->whereNull('order_items.parent_order_item_id')
            ->select('order_items.item_name', DB::raw('SUM(order_items.quantity) as qty'), DB::raw('SUM(order_items.subtotal) as revenue'))
            ->groupBy('order_items.item_name')
            ->orderByDesc('qty')
            ->limit(max(1, min(20, (int) ($args['limit'] ?? 10))))
            ->get();

        return ['period' => $label, 'items' => $rows->map(fn ($row) => ['item' => $row->item_name, 'qty' => (int) $row->qty, 'revenue' => self::money($row->revenue)])];
    }

    protected static function menuItems(array $args): array
    {
        $query = MenuItem::with('menuCategory')->orderBy('name');
        if ($search = trim((string) ($args['search'] ?? ''))) {
            $query->where('name', 'like', '%'.$search.'%');
        }
        if ($category = trim((string) ($args['category'] ?? ''))) {
            $query->whereHas('menuCategory', fn ($q) => $q->where('name', 'like', '%'.$category.'%'));
        }
        if (MenuItemAvailability::tryFrom((string) ($args['availability'] ?? ''))) {
            $query->where('availability_status', $args['availability']);
        }

        $total = (clone $query)->count();
        $items = $query->limit(40)->get();

        // One to three items: a button to edit each; more: Menu Management.
        $links = $items->count() <= 3 && $items->isNotEmpty()
            ? $items->map(fn (MenuItem $item) => ['label' => __('Edit :item', ['item' => $item->name]), 'url' => route('menu-items.edit', $item)])->all()
            : [self::pageLink('menu_management')];

        return ['_links' => $links, 'total_matching' => $total, 'items' => $items->map(fn (MenuItem $item) => [
            'name' => $item->name,
            'category' => $item->menuCategory?->name,
            'price' => self::money($item->price),
            'availability' => $item->availability_status->label(),
        ])];
    }

    protected static function spacesStatus(): array
    {
        $spaces = Space::with(['area', 'category'])->get();

        return [
            'counts' => collect(SpaceStatus::cases())->mapWithKeys(fn (SpaceStatus $status) => [$status->value => $spaces->where('status', $status)->count()]),
            'occupied' => $spaces->where('status', SpaceStatus::Occupied)->map(fn (Space $space) => trim(($space->area?->name ? $space->area->name.' · ' : '').$space->name))->values(),
        ];
    }

    protected static function massageSummary(array $args): array
    {
        [$start, $end, $label] = self::period($args['period'] ?? null);
        $paid = MassageOrder::where('status', MassageOrderStatus::Paid)->whereBetween('paid_at', [$start, $end]);

        return [
            'period' => $label,
            'paid_orders' => (clone $paid)->count(),
            'revenue' => self::money((clone $paid)->sum('total_amount')),
            'unpaid_open_orders' => MassageOrder::where('status', MassageOrderStatus::Pending)->count(),
            'unpaid_orders' => MassageOrder::where('status', MassageOrderStatus::Pending)->latest()->limit(20)->get()
                ->map(fn (MassageOrder $order) => ['order_number' => $order->orderNumber(), 'total' => self::money($order->total_amount), 'created' => $order->created_at->format('M j, g:i A')]),
            'services_sold' => DB::table('massage_order_items')
                ->join('massage_orders', 'massage_orders.id', '=', 'massage_order_items.massage_order_id')
                ->where('massage_orders.status', MassageOrderStatus::Paid->value)
                ->whereBetween('massage_orders.paid_at', [$start, $end])
                ->select('massage_order_items.name', DB::raw('SUM(massage_order_items.quantity) as qty'))
                ->groupBy('massage_order_items.name')
                ->orderByDesc('qty')
                ->pluck('qty', 'name'),
            'services' => MassageService::ordered()->get()->map(fn (MassageService $service) => [
                'name' => $service->name,
                'price' => $service->priceLabel(),
                'available' => (bool) $service->is_available,
            ]),
        ];
    }

    /** One to three orders: a button to open each; more: Order Management. */
    protected static function orderLinks($orders): array
    {
        return $orders->count() <= 3 && $orders->isNotEmpty()
            ? $orders->map(fn (Order $order) => ['label' => __('Open :order', ['order' => $order->orderNumber()]), 'url' => route('orders.show', $order)])->values()->all()
            : [self::pageLink('order_management')];
    }

    /** Raw totals of a period, for sales_compare. */
    protected static function totals(?string $period): array
    {
        [$start, $end, $label] = self::period($period);
        $paid = ReportController::countableOrders()->whereBetween('created_at', [$start, $end])->where('payment_status', PaymentStatus::Paid);
        $massage = MassageOrder::where('status', MassageOrderStatus::Paid)->whereBetween('paid_at', [$start, $end]);

        return [
            'label' => $label,
            'restaurant' => (float) (clone $paid)->sum('total_amount'),
            'restaurant_orders' => (clone $paid)->count(),
            'massage' => (float) (clone $massage)->sum('total_amount'),
            'massage_orders' => (clone $massage)->count(),
        ];
    }

    protected static function salesCompare(array $args): array
    {
        $a = self::totals($args['period'] ?? 'today');
        $b = self::totals($args['compare_to'] ?? 'yesterday');
        $change = fn (float $now, float $before) => [
            'difference' => ($now >= $before ? '+' : '-').self::money(abs($now - $before)),
            'percent' => $before > 0 ? sprintf('%+.1f%%', ($now - $before) / $before * 100) : 'n/a (nothing to compare with)',
        ];

        return [
            'period' => $a['label'],
            'compared_to' => $b['label'],
            'restaurant' => ['revenue' => self::money($a['restaurant']), 'paid_orders' => $a['restaurant_orders'], 'before' => self::money($b['restaurant']), 'before_paid_orders' => $b['restaurant_orders']] + $change($a['restaurant'], $b['restaurant']),
            'massage' => ['revenue' => self::money($a['massage']), 'paid_orders' => $a['massage_orders'], 'before' => self::money($b['massage']), 'before_paid_orders' => $b['massage_orders']] + $change($a['massage'], $b['massage']),
            'note' => 'A period that is not over yet (e.g. today) is compared as it stands now.',
        ];
    }

    protected static function quotations(array $args): array
    {
        [$start, $end, $label] = self::period($args['period'] ?? 'next_7_days');
        $query = Quotation::with(['space', 'area'])->whereBetween('scheduled_for', [$start, $end])->orderBy('scheduled_for');
        if (empty($args['include_cancelled'])) {
            $query->where('status', '!=', QuotationStatus::Cancelled);
        }
        $quotations = $query->limit(30)->get();

        return [
            '_links' => $quotations->count() <= 3 && $quotations->isNotEmpty()
                ? $quotations->map(fn (Quotation $quotation) => ['label' => __('Open :quotation', ['quotation' => $quotation->quotation_number]), 'url' => route('quotations.show', $quotation)])->all()
                : [self::pageLink('quotations')],
            'period' => $label,
            'count' => $quotations->count(),
            'quotations' => $quotations->map(fn (Quotation $quotation) => [
                'number' => $quotation->quotation_number,
                'customer' => $quotation->customer_name,
                'contact' => $quotation->customer_contact,
                'scheduled_for' => $quotation->scheduled_for?->format('D, M j g:i A'),
                'table' => trim(($quotation->area?->name ? $quotation->area->name.' · ' : '').($quotation->space?->name ?? '')) ?: null,
                'amount' => self::money($quotation->subtotal),
                'status' => $quotation->status->label(),
                'notes' => $quotation->notes,
            ]),
        ];
    }

    /** The Kitchen display's slips, oldest first (the same three lanes). */
    public static function kitchenSlips()
    {
        return Order::with(['space', 'area', 'spaceCategory'])
            ->whereIn('status', [OrderStatus::Pending, OrderStatus::Preparing, OrderStatus::Ready])
            ->whereNull('merged_into_order_id')
            ->withoutFutureReservations()
            ->oldest()
            ->get();
    }

    protected static function kitchenQueue(): array
    {
        $slips = self::kitchenSlips();

        return [
            'counts' => collect([OrderStatus::Pending, OrderStatus::Preparing, OrderStatus::Ready])->mapWithKeys(fn (OrderStatus $status) => [$status->label() => $slips->where('status', $status)->count()]),
            'waiting_over_20_minutes' => $slips->whereIn('status', [OrderStatus::Pending, OrderStatus::Preparing])->filter(fn (Order $order) => $order->created_at->diffInMinutes(now()) > 20)->count(),
            'slips' => $slips->take(30)->map(fn (Order $order) => [
                'order_number' => $order->orderNumber(),
                'location' => $order->locationLabel(),
                'stage' => $order->status->label(),
                'minutes_waiting' => (int) $order->created_at->diffInMinutes(now()),
            ])->values(),
        ];
    }

    protected static function marketPrices(array $args): array
    {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($args['date'] ?? '')) ? Carbon::parse($args['date']) : today();
        $items = MenuItem::where('pricing_type', PricingType::PerKilo)->with('menuCategory')->orderBy('name')->get();
        $set = DailyMarketPrice::with('setBy')->where('effective_date', $date->toDateString())->get()->keyBy('menu_item_id');

        return [
            'date' => $date->format('D, M j, Y'),
            'weighed_items' => $items->count(),
            'set' => $items->filter(fn (MenuItem $item) => $set->has($item->id))->count(),
            'not_set' => $items->reject(fn (MenuItem $item) => $set->has($item->id))->count(),
            'items' => $items->map(fn (MenuItem $item) => [
                'name' => $item->name,
                'category' => $item->menuCategory?->name,
                'price_per_kilo' => $set->has($item->id) ? self::money($set[$item->id]->price_per_kilo) : null,
                'set_by' => $set->get($item->id)?->setBy?->name,
                'standing_rate_if_not_set' => self::money($item->price_per_kilo),
            ]),
        ];
    }

    protected static function accountCharges(array $args): array
    {
        [$start, $end, $label] = self::period($args['period'] ?? null);
        $methods = match ((string) ($args['type'] ?? 'all')) {
            'room' => [PaymentMethod::RoomCharge],
            'locker' => [PaymentMethod::LockerCharge],
            'kubo' => [PaymentMethod::KuboCharge],
            default => [PaymentMethod::RoomCharge, PaymentMethod::LockerCharge, PaymentMethod::KuboCharge],
        };
        $values = array_map(fn (PaymentMethod $method) => $method->value, $methods);

        $restaurant = OrderPayment::with('order')->where('status', OrderPaymentStatus::Recorded->value)
            ->whereIn('payment_method', $values)->whereBetween('received_at', [$start, $end])->orderBy('received_at')->get();
        $massage = MassagePayment::with('order')->where('status', OrderPaymentStatus::Recorded->value)
            ->whereIn('payment_method', $values)->whereBetween('received_at', [$start, $end])->orderBy('received_at')->get();

        $row = fn ($payment, string $outlet) => [
            'outlet' => $outlet,
            'charged_to' => $payment->chargeTargetLabel(),
            'guest' => $payment->guest_name,
            'amount' => self::money($payment->amount),
            'order' => $payment->order?->orderNumber(),
            'when' => $payment->received_at?->format('M j, g:i A'),
            'settled' => $payment->settlementLabel(),
        ];

        $all = $restaurant->map(fn ($payment) => $row($payment, 'Restaurant'))->concat($massage->map(fn ($payment) => $row($payment, 'Massage')));

        return [
            'period' => $label,
            'count' => $all->count(),
            'total' => self::money($restaurant->sum('amount') + $massage->sum('amount')),
            'charges' => $all->take(40)->values(),
            'note' => 'Room, locker and kubo charges are paid by the guest at front desk checkout. The system does not record whether the front desk has collected them.',
        ];
    }

    /**
     * Mochi's reminders for this user (the speech bubble, via
     * AssistantController::alerts): quick counts only, no Gemini. Each has
     * the question asked when it is clicked.
     *
     * @return array<int, array{key: string, text: string, question: string}>
     */
    public static function alertsFor(User $user): array
    {
        $alerts = [];

        if ($user->worksIn(Department::Restaurant)) {
            // The Kitchen display's slips; Pending is its first lane.
            $kitchen = self::kitchenSlips();

            $pending = $kitchen->where('status', OrderStatus::Pending)->count();
            if ($pending > 0) {
                $alerts[] = ['key' => 'pending-'.$pending, 'text' => trans_choice('{1} There is 1 pending order.|[2,*] There are :count pending orders.', $pending, ['count' => $pending]), 'question' => __('Which orders are pending right now?')];
            }

            if ($kitchen->isNotEmpty()) {
                $alerts[] = ['key' => 'kitchen-'.$kitchen->count(), 'text' => trans_choice('{1} There is 1 slip in the kitchen.|[2,*] There are :count slips in the kitchen.', $kitchen->count(), ['count' => $kitchen->count()]), 'question' => __('What slips are in the kitchen right now?')];
            }

            $unpaid = ReportController::countableOrders()
                ->where('status', '!=', OrderStatus::Cancelled)
                ->where('payment_status', PaymentStatus::Unpaid)
                ->count();
            if ($unpaid > 0) {
                $alerts[] = ['key' => 'unpaid-'.$unpaid, 'text' => trans_choice('{1} There is 1 unpaid order.|[2,*] There are :count unpaid orders.', $unpaid, ['count' => $unpaid]), 'question' => __('Which orders are unpaid? Include every status and day.')];
            }
        }

        if ($user->worksIn(Department::Services)) {
            $unpaid = MassageOrder::where('status', MassageOrderStatus::Pending)->count();
            if ($unpaid > 0) {
                $alerts[] = ['key' => 'massage-unpaid-'.$unpaid, 'text' => trans_choice('{1} There is 1 unpaid Massage order.|[2,*] There are :count unpaid Massage orders.', $unpaid, ['count' => $unpaid]), 'question' => __('Which Massage orders are still unpaid?')];
            }
        }

        return $alerts;
    }

    protected static function usersList(): array
    {
        return ['users' => User::orderBy('name')->get()->map(fn (User $user) => [
            'name' => $user->name,
            'role' => $user->role->value,
            'department' => $user->department?->value,
            'active' => (bool) $user->is_active,
        ])];
    }

    protected static function systemStatus(): array
    {
        return [
            'maintenance_mode' => Setting::maintenanceIsOn(),
            'menu_items' => MenuItem::count(),
            'orders_total' => Order::count(),
            'open_orders' => Order::whereNotIn('status', ['completed', 'cancelled'])->count(),
            'users' => User::count(),
            'now' => now()->format('D, M j Y g:i A'),
        ];
    }

    /** Exact name first, then the only one containing it. */
    protected static function findByName($query, string $name)
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return (clone $query)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            ?? ((clone $query)->where('name', 'like', '%'.$name.'%')->count() === 1
                ? (clone $query)->where('name', 'like', '%'.$name.'%')->first()
                : null);
    }
}
