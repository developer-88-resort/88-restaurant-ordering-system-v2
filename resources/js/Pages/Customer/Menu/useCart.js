import { useEffect, useRef, useState } from 'react';
import { addOnsKey, cartLineTotal } from './cartLine';

const CART_EXPIRY_MS = 4 * 60 * 60 * 1000;

function normalizeNotes(notes) {
    return notes?.trim() || null;
}

function normalizeAddOns(addOns) {
    return (addOns ?? []).map((a) => ({ id: a.id, name: a.name, price: a.price, qty: a.qty }));
}

export default function useCart(spaceId) {
    const storageKey = `cart_space_${spaceId}`;
    const [cart, setCart] = useState([]);
    const [notes, setNotes] = useState('');
    // Load and save are two separate effects; without this guard the save
    // effect (which fires on every [cart, notes] change, including the
    // very first render) would overwrite the just-loaded localStorage cart
    // with the initial empty array before the load effect below gets a
    // chance to run — Alpine's synchronous x-init never had this race.
    const hydratedRef = useRef(false);

    useEffect(() => {
        try {
            const raw = localStorage.getItem(storageKey);
            if (raw) {
                const saved = JSON.parse(raw);
                if (saved && Array.isArray(saved.items) && Date.now() - saved.savedAt <= CART_EXPIRY_MS) {
                    setCart(saved.items);
                    if (saved.notes) setNotes(saved.notes);
                } else {
                    localStorage.removeItem(storageKey);
                }
            }
        } catch {
            localStorage.removeItem(storageKey);
        }
        hydratedRef.current = true;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [storageKey]);

    useEffect(() => {
        if (!hydratedRef.current) return;
        localStorage.setItem(storageKey, JSON.stringify({ savedAt: Date.now(), items: cart, notes }));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [cart, notes, storageKey]);

    const addItem = (item) => {
        const variantId = item.variantId ?? null;
        const lineNotes = normalizeNotes(item.notes);
        const qty = item.qty ?? 1;
        const addOns = normalizeAddOns(item.addOns);

        setCart((current) => {
            // Different special instructions must stay separate lines (e.g.
            // one no-onions + one plain) even for the same item/variant —
            // merging them would hide the distinction from the kitchen. A
            // different add-on selection is the same kind of distinction.
            const index = current.findIndex(
                (line) => line.id === item.id && line.variantId === variantId && line.notes === lineNotes && addOnsKey(line.addOns) === addOnsKey(addOns),
            );
            if (index === -1) {
                return [...current, { id: item.id, variantId, name: item.name, price: item.price, qty, notes: lineNotes, addOns }];
            }
            const next = [...current];
            next[index] = { ...next[index], qty: next[index].qty + qty };
            return next;
        });
    };

    const increment = (index) => setCart((current) => current.map((line, i) => (i === index ? { ...line, qty: line.qty + 1 } : line)));

    const decrement = (index) =>
        setCart((current) => {
            const line = current[index];
            if (!line) return current;
            if (line.qty - 1 <= 0) return current.filter((_, i) => i !== index);
            return current.map((l, i) => (i === index ? { ...l, qty: l.qty - 1 } : l));
        });

    const removeByMenuItemId = (menuItemId) => setCart((current) => current.filter((line) => line.id !== menuItemId));

    const clear = () => {
        setCart([]);
        setNotes('');
        localStorage.removeItem(storageKey);
    };

    const total = cart.reduce((sum, line) => sum + cartLineTotal(line), 0);
    const count = cart.reduce((sum, line) => sum + line.qty, 0);

    return {
        cart,
        notes,
        setNotes,
        addItem,
        increment,
        decrement,
        removeByMenuItemId,
        clear,
        total,
        count,
        isEmpty: cart.length === 0,
    };
}
