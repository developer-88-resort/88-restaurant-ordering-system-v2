import EmptyState from '@/Components/EmptyState';
import CustomerLayout from '@/Layouts/CustomerLayout';
import { useTranslation } from '@/lib/i18n';
import { turboCleanup } from '@/lib/turbo-cleanup';
import { useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AddConfirmModal from './Menu/AddConfirmModal';
import CartDrawer from './Menu/CartDrawer';
import CategoryChipBar from './Menu/CategoryChipBar';
import DesktopPromotionRail from './Menu/DesktopPromotionRail';
import ItemAddedToasts from './Menu/ItemAddedToasts';
import ItemCard from './Menu/ItemCard';
import OrderConfirmModal from './Menu/OrderConfirmModal';
import PromotionCarousel from './Menu/PromotionCarousel';
import TableSessionPanel from './Menu/TableSessionPanel';
import useCart from './Menu/useCart';
import useCategoryScrollspy from './Menu/useCategoryScrollspy';

function isOrderableStatus(status) {
    return status === 'available' || status === 'seasonal';
}

export default function Menu({
    space,
    submit_url: submitUrl,
    categories,
    promotions = [],
    customer_name: customerName,
    guest_label: guestLabel,
    join_qr_url: joinQrUrl,
    previous_orders: previousOrders,
    session_order_count: sessionOrderCount,
    session_total: sessionTotal,
}) {
    const t = useTranslation();
    const cartApi = useCart(space.id);
    const { selectedCategory, selectCategory, chipBarRef, menuTopRef } = useCategoryScrollspy();

    const [itemAvailability, setItemAvailability] = useState(() => {
        const map = {};
        categories.forEach((category) => category.items.forEach((item) => { map[item.id] = item.availability_status; }));
        return map;
    });
    const [addConfirmItem, setAddConfirmItem] = useState(null);
    const [cartOpen, setCartOpen] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [itemToasts, setItemToasts] = useState([]);

    const cartRef = useRef(cartApi.cart);
    useEffect(() => {
        cartRef.current = cartApi.cart;
    }, [cartApi.cart]);

    const [idempotencyKey] = useState(() => (window.crypto?.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(36).slice(2)}`));
    const form = useForm({
        notes: '',
        customer_name: customerName ?? '',
        idempotency_key: idempotencyKey,
    });

    const pushItemToast = (name, type) => {
        const id = Date.now() + Math.random();
        setItemToasts((current) => [...current, { id, name, type }]);
        setTimeout(() => setItemToasts((current) => current.filter((toast) => toast.id !== id)), 2200);
    };

    // Realtime availability — public 'menu' channel, same event this app's
    // own MenuItems/Index.jsx already listens on (Pages/MenuItems/Index.jsx).
    useEffect(() => {
        if (!window.Echo) return undefined;

        const channel = window.Echo.channel('menu');
        channel.listen('.MenuItemAvailabilityChanged', (event) => {
            setItemAvailability((current) => ({ ...current, [event.menu_item_id]: event.availability_status }));

            if (!isOrderableStatus(event.availability_status)) {
                const inCart = cartRef.current.find((line) => line.id === event.menu_item_id);
                if (inCart) {
                    cartApi.removeByMenuItemId(event.menu_item_id);
                    pushItemToast(inCart.name, 'unavailable');
                }
            }
        });

        const leave = () => window.Echo?.leave('menu');
        turboCleanup(leave);

        return leave;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const handleAddToCart = (line) => {
        cartApi.addItem(line);
        pushItemToast(line.name, 'success');
        setAddConfirmItem(null);
    };

    const handleReorderBatch = (items) => {
        let added = 0;
        items.forEach((item) => {
            if (!item.available) {
                pushItemToast(item.name, 'unavailable');
                return;
            }
            cartApi.addItem({ id: item.menu_item_id, variantId: item.variant_id ?? null, name: item.name, price: item.price, qty: item.qty, notes: null });
            added++;
        });
        if (added > 0) {
            setCartOpen(true);
        }
    };

    const submitOrder = () => {
        form.transform((data) => ({
            ...data,
            notes: cartApi.notes,
            items: cartApi.cart.map((line) => ({
                menu_item_id: line.id,
                menu_item_variant_id: line.variantId,
                notes: line.notes,
                quantity: line.qty,
                // Price is never sent — the server re-derives it from the
                // live MenuItemAddOn, same rule as menu_item_variant_id.
                add_ons: (line.addOns ?? []).map((addOn) => ({ id: addOn.id, quantity: addOn.qty })),
            })),
        }));
        form.post(submitUrl, {
            preserveScroll: true,
            onSuccess: () => cartApi.clear(),
            onError: () => {
                setConfirmOpen(false);
                setCartOpen(true);
            },
        });
    };

    const perKiloItems = categories
        .flatMap((category) => category.items.filter((item) => item.is_per_kilo))
        .sort((a, b) => (a.weighed_sort_order ?? Infinity) - (b.weighed_sort_order ?? Infinity));
    const categoriesWithVisibleItems = categories
        .map((category) => ({ ...category, visibleItems: category.items.filter((item) => !item.is_per_kilo) }))
        .filter((category) => category.visibleItems.length > 0);
    return (
        <CustomerLayout title={t('Menu')} locationLabel={`${space.area_name} - ${space.name}`}>
            <ItemAddedToasts toasts={itemToasts} onDismiss={(id) => setItemToasts((current) => current.filter((toast) => toast.id !== id))} />

            {customerName && (
                <p className="mx-auto max-w-5xl px-4 pt-3 text-sm text-[#8A7B6D]">
                    {t("Hi :name! Here's the menu — add whatever you like.").replace(':name', customerName)}
                </p>
            )}

            <TableSessionPanel
                space={space}
                guestLabel={guestLabel}
                sessionOrderCount={sessionOrderCount}
                sessionTotal={sessionTotal}
                previousOrders={previousOrders}
                joinQrUrl={joinQrUrl}
                onReorderBatch={handleReorderBatch}
            />

            <div className="mx-auto max-w-[1380px] lg:grid lg:grid-cols-[150px_minmax(0,1fr)_150px] lg:items-start lg:gap-4 lg:px-4">
                <DesktopPromotionRail promotions={promotions} side="Left" startIndex={0} />

                <div className="min-w-0">
                    <PromotionCarousel promotions={promotions} />

                    {categories.length > 0 && (
                        <CategoryChipBar categories={categories} selectedCategory={selectedCategory} onSelect={selectCategory} chipBarRef={chipBarRef} />
                    )}

                    <div ref={menuTopRef} className={`mx-auto max-w-5xl space-y-8 px-4 py-6 scroll-mt-32 ${cartApi.isEmpty ? 'pb-8' : 'pb-28'}`}>
                {categories.length === 0 ? (
                    <EmptyState
                        title={t('Nothing on the menu right now')}
                        description={t('Please ask our staff for assistance with your order.')}
                    />
                ) : (
                    <>
                        {categoriesWithVisibleItems.map((category) => {
                            const optionCount = category.visibleItems.reduce((sum, item) => sum + (item.has_variants ? item.variants.length : 1), 0);

                            return (
                                <div key={category.id} id={`category-${category.id}`} data-category-id={category.id} className="scroll-mt-32">
                                    <div className="mb-3 flex items-center gap-2.5">
                                        <span className="h-5 w-1 rounded-full bg-[#8A3330]" />
                                        <h3 className="font-semibold text-gray-900">{category.name}</h3>
                                        <span className="rounded-full border border-[#E5DDD0] bg-[#F7F0E3] px-2 py-0.5 text-xs font-medium text-[#8A7B6D]">
                                            {optionCount} {optionCount === 1 ? t('option') : t('options')}
                                        </span>
                                    </div>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                        {category.visibleItems.map((item) => (
                                            <ItemCard key={item.id} item={item} status={itemAvailability[item.id]} onSelect={setAddConfirmItem} />
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </>
                )}
                    </div>

                    {perKiloItems.length > 0 && (
                        <div className="mx-auto max-w-5xl px-4 pb-6">
                            <div className="rounded-2xl border border-dashed border-[#D9CCBA] bg-[#FBF7EF] p-4">
                                <div className="mb-3 flex items-start gap-2.5">
                                    <span className="text-lg leading-none">🐟</span>
                                    <div className="min-w-0">
                                        <h3 className="font-semibold text-gray-900">{t('By the Kilo')}</h3>
                                        <p className="mt-0.5 text-xs leading-5 text-[#8A7B6D]">
                                            {t('Priced by weight and prepared to order — visit our counter so staff can pick, weigh, and cook it for you.')}
                                        </p>
                                    </div>
                                </div>
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                    {perKiloItems.map((item) => (
                                        <ItemCard key={item.id} item={item} status={itemAvailability[item.id]} onSelect={setAddConfirmItem} />
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                <DesktopPromotionRail promotions={promotions} side="Right" startIndex={4} />
            </div>

            <CartDrawer
                cart={cartApi.cart}
                notes={cartApi.notes}
                onNotesChange={cartApi.setNotes}
                cartOpen={cartOpen}
                onToggle={setCartOpen}
                total={cartApi.total}
                count={cartApi.count}
                isEmpty={cartApi.isEmpty}
                onIncrement={cartApi.increment}
                onDecrement={cartApi.decrement}
                onPlaceOrder={() => {
                    setCartOpen(false);
                    setConfirmOpen(true);
                }}
                errors={form.errors}
            />

            <AddConfirmModal
                item={addConfirmItem}
                status={addConfirmItem ? itemAvailability[addConfirmItem.id] : null}
                onClose={() => setAddConfirmItem(null)}
                onConfirm={handleAddToCart}
            />

            <OrderConfirmModal
                show={confirmOpen}
                cart={cartApi.cart}
                notes={cartApi.notes}
                total={cartApi.total}
                count={cartApi.count}
                location={`${space.area_name} · ${space.name}`}
                submitting={form.processing}
                onClose={() => setConfirmOpen(false)}
                onSubmit={submitOrder}
            />
        </CustomerLayout>
    );
}
