import { useCallback, useMemo, useState } from 'react';

/**
 * THE single source of truth for the scale reading.
 *
 * Every gram the wizard knows about enters through `setGrams` and lives in
 * one place. Nothing else in the UI keeps its own copy, and no component
 * writes grams directly — which is what makes swapping the on-screen keypad
 * for a live digital-scale feed in Phase 3 a matter of calling `setGrams`
 * from the serial/websocket handler instead of from a button press. Spread
 * the weight across several handlers and that swap becomes a rewrite.
 */
export function useWeighInput({ minWeightGrams, weightStepGrams, allowTare }) {
    // Held as strings so a half-typed "1" doesn't become the number 1 and
    // fight the user's next keystroke.
    const [grossRaw, setGrossRaw] = useState('');
    const [tareRaw, setTareRaw] = useState('');
    const [pieces, setPieces] = useState(1);
    // Which field the keypad is currently driving.
    const [target, setTarget] = useState('gross');

    const gross = Number(grossRaw) || 0;
    const tare = allowTare ? Number(tareRaw) || 0 : 0;
    const net = Math.max(0, gross - tare);

    /** The one way any gram value ever changes. */
    const setGrams = useCallback((field, value) => {
        const clean = String(value ?? '').replace(/[^0-9]/g, '').slice(0, 6);
        if (field === 'tare') setTareRaw(clean);
        else setGrossRaw(clean);
    }, []);

    const pressDigit = useCallback(
        (digit) => setGrams(target, (target === 'tare' ? tareRaw : grossRaw) + digit),
        [target, tareRaw, grossRaw, setGrams],
    );

    const pressBackspace = useCallback(
        () => setGrams(target, (target === 'tare' ? tareRaw : grossRaw).slice(0, -1)),
        [target, tareRaw, grossRaw, setGrams],
    );

    const clear = useCallback(() => setGrams(target, ''), [target, setGrams]);

    const reset = useCallback(() => {
        setGrossRaw('');
        setTareRaw('');
        setPieces(1);
        setTarget('gross');
    }, []);

    const errors = useMemo(() => {
        const list = [];
        if (gross === 0) return list;

        if (allowTare && tare >= gross && tare > 0) {
            list.push({
                key: 'tare',
                message: 'The tare weight must be less than the weight on the scale.',
            });
        }
        if (net < minWeightGrams) {
            list.push({
                key: 'min',
                message: `Minimum for this item is ${minWeightGrams} g — this reads ${net} g.`,
            });
        }
        if (weightStepGrams > 1 && net % weightStepGrams !== 0) {
            list.push({
                key: 'step',
                message: `Weight must be in steps of ${weightStepGrams} g. Nearest are ${
                    Math.floor(net / weightStepGrams) * weightStepGrams
                } g and ${Math.ceil(net / weightStepGrams) * weightStepGrams} g.`,
            });
        }
        return list;
    }, [gross, tare, net, minWeightGrams, weightStepGrams, allowTare]);

    return {
        grossRaw,
        tareRaw,
        gross,
        tare,
        net,
        pieces,
        setPieces,
        target,
        setTarget,
        setGrams,
        pressDigit,
        pressBackspace,
        clear,
        reset,
        errors,
        isValid: gross > 0 && errors.length === 0,
    };
}

/**
 * Mirrors App\Support\WeighedLinePricer for the on-screen preview only.
 * The server always recomputes what it actually bills — this exists so the
 * number under the customer's nose updates as the scale moves.
 */
export function previewTotal({ net, pricePerKilo, surcharge = 0, pieces = 1 }) {
    const base = Math.round((net / 1000) * pricePerKilo * 100) / 100;
    return base + surcharge * Math.max(1, pieces);
}

export const peso = (value) =>
    `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
