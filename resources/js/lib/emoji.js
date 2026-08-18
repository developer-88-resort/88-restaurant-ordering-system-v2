// Lazily fetches the Fluent Emoji SVG data (resources/js/data/fluent-emoji-bodies.json,
// generated from @iconify-json/fluent-emoji-flat — see that generation note in
// the data file's sibling README if one exists) exactly once per page load,
// no matter how many components ask for it.
let bodiesPromise = null;

export function loadEmojiBodies() {
    if (!bodiesPromise) {
        bodiesPromise = import('@/data/fluent-emoji-bodies.json').then((mod) => mod.default);
    }
    return bodiesPromise;
}

const segmenter = typeof Intl !== 'undefined' && Intl.Segmenter
    ? new Intl.Segmenter('en', { granularity: 'grapheme' })
    : null;

/**
 * Splits text into a run of plain-text strings and { emoji } markers, so a
 * caller can render each piece as either a text node or a Fluent Emoji SVG.
 * Graphemes (not raw characters) are the unit of matching — this is what
 * correctly keeps multi-codepoint sequences (ZWJ families, flags, skin
 * tones) together instead of splitting them mid-glyph.
 *
 * @returns {Array<string | { emoji: string }>}
 */
export function splitTextAndEmoji(text, bodies) {
    if (!segmenter || !bodies) return [text];

    const pieces = [];
    let textRun = '';

    for (const { segment } of segmenter.segment(text)) {
        if (bodies[segment]) {
            if (textRun) {
                pieces.push(textRun);
                textRun = '';
            }
            pieces.push({ emoji: segment });
        } else {
            textRun += segment;
        }
    }
    if (textRun) pieces.push(textRun);

    return pieces;
}
