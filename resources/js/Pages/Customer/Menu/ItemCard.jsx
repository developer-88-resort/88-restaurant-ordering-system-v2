import ItemCardBody from './ItemCardBody';

// Per-kilo items (pricing_type = per_kilo) are priced from an actual
// recorded weight at the counter, never a flat tappable price — enforced
// here (never rendered as a <button>) rather than trusting a parent to
// simply withhold a click handler.
export default function ItemCard({ item, status, onSelect }) {
    const orderable = status === 'available' || status === 'seasonal';

    if (item.is_per_kilo) {
        return (
            <div className={`flex w-full items-center gap-3 rounded-xl border border-[#E5DDD0] bg-white p-3 shadow-sm transition ${orderable ? '' : 'opacity-60'}`}>
                <ItemCardBody item={item} status={status} />
            </div>
        );
    }

    return (
        <button
            type="button"
            onClick={() => onSelect(item)}
            className={`flex w-full items-center gap-3 rounded-xl border border-[#E5DDD0] bg-white p-3 text-left shadow-sm transition ${
                orderable ? 'hover:border-[#8A3330] hover:shadow-md' : 'opacity-60'
            }`}
        >
            <ItemCardBody item={item} status={status} />
        </button>
    );
}
