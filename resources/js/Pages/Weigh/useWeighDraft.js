import { useCallback, useMemo, useState } from 'react';

/**
 * THE single source of truth for what came off the counter scale.
 *
 * The scale display now shows TWO numbers — the weight and the amount —
 * and staff key in both exactly as shown. Every digit enters through
 * `pressDigit`/`pressBackspace` and lives in one of two buffers here;
 * nothing else in the UI keeps its own copy. That is what makes swapping
 * the on-screen keypad for a live digital-scale feed in Phase 3 a matter of
 * calling `setGrams`/`setAmount` from the serial/websocket handler instead
 * of from a button press — spread the numbers across several handlers and
 * that swap becomes a rewrite.
 */
export function useWeighInput({ minWeightGrams, blocksBelowMinimum = true }) {
    // Held as digit strings so a half-typed entry doesn't become a number
    // and fight the user's next keystroke. The amount buffer holds
    // CENTAVOS — typing 1,7,7,0,0 reads back as ₱177.00, the same entry
    // pattern as a calculator or a POS numpad.
    const [gramsRaw, setGramsRaw] = useState('');
    const [centavosRaw, setCentavosRaw] = useState('');
    const [pieces, setPieces] = useState(1);
    // Which buffer the keypad is currently driving.
    const [target, setTarget] = useState('grams');

    // The scale's TARE button already produces a net figure, so what is
    // keyed in IS the net weight — the app never deducts a tare itself.
    const netGrams = Number(gramsRaw) || 0;
    const amountCharged = (Number(centavosRaw) || 0) / 100;

    const setGrams = useCallback((value) => {
        setGramsRaw(String(value ?? '').replace(/[^0-9]/g, '').slice(0, 6));
    }, []);

    const setAmount = useCallback((value) => {
        setCentavosRaw(String(value ?? '').replace(/[^0-9]/g, '').slice(0, 9));
    }, []);

    const pressDigit = useCallback(
        (digit) => (target === 'grams' ? setGrams(gramsRaw + digit) : setAmount(centavosRaw + digit)),
        [target, gramsRaw, centavosRaw, setGrams, setAmount],
    );

    const pressBackspace = useCallback(
        () => (target === 'grams' ? setGrams(gramsRaw.slice(0, -1)) : setAmount(centavosRaw.slice(0, -1))),
        [target, gramsRaw, centavosRaw, setGrams, setAmount],
    );

    const clear = useCallback(
        () => (target === 'grams' ? setGrams('') : setAmount('')),
        [target, setGrams, setAmount],
    );

    const reset = useCallback(() => {
        setGramsRaw('');
        setCentavosRaw('');
        setPieces(1);
        setTarget('grams');
    }, []);

    const errors = useMemo(() => {
        const list = [];
        if (netGrams === 0) return list;

        // No step snapping — the scale's actual reading is accepted. Under
        // the minimum only stops the line when the admin says it should.
        if (netGrams < minWeightGrams && blocksBelowMinimum) {
            list.push({
                key: 'min',
                message: `Minimum for this item is ${minWeightGrams} g — this reads ${netGrams} g.`,
            });
        }
        return list;
    }, [netGrams, minWeightGrams, blocksBelowMinimum]);

    return {
        gramsRaw,
        netGrams,
        centavosRaw,
        amountCharged,
        amountFormatted: formatCentavos(centavosRaw),
        pieces,
        setPieces,
        target,
        setTarget,
        pressDigit,
        pressBackspace,
        clear,
        reset,
        // Set a buffer directly to a known value in one atomic update — for
        // callers computing a value themselves (e.g. "use the expected
        // amount") rather than emulating a sequence of keypad taps, which
        // silently breaks: several `pressDigit` calls made back-to-back in
        // one handler all read the same pre-update buffer, so they don't
        // accumulate digit-by-digit the way real, separate button clicks do.
        setGrams,
        setAmount,
        errors,
        isValid: netGrams > 0 && amountCharged > 0 && errors.length === 0,
    };
}

/** "17700" -> "177.00" — a centavo buffer read back as pesos. */
function formatCentavos(raw) {
    const digits = (raw || '').padStart(3, '0');
    const whole = digits.slice(0, -2).replace(/^0+(?=\d)/, '');
    return `${whole || '0'}.${digits.slice(-2)}`;
}

/**
 * Mirrors App\Support\WeighedLinePricer for on-screen previews only — the
 * menu item form's "smallest sellable" figure, and the reference-rate
 * display at the weigh station. The server always recomputes what it
 * actually bills; nothing here ever decides a real charge.
 */
export function previewTotal({ net, pricePerKilo, surcharge = 0, pieces = 1 }) {
    const base = Math.round((net / 1000) * pricePerKilo * 100) / 100;
    return base + surcharge * Math.max(1, pieces);
}

export const peso = (value) =>
    `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
