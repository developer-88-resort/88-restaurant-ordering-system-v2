import animatedEmoji from '@/data/animated-emoji.json';

// Renders one emoji, in priority order: an animated Fluent Emoji WebP for
// the curated ~60 "favorites" (see public/emoji-animated/, sourced from
// @lobehub/fluent-emoji-anim-1/2/3 — real animated artwork, not a video
// library, just an <img> that autoplays like a GIF); otherwise the static
// Fluent Emoji SVG artwork when we have it bundled (see resources/js/lib/emoji.js);
// otherwise the plain Unicode character — for the small remainder not in
// either set, or while the static data is still loading — so nothing ever
// fails to render.
export default function Emoji({ char, bodies, className = 'h-[1.2em] w-[1.2em] align-[-0.25em]' }) {
    const animatedUrl = animatedEmoji[char];

    if (animatedUrl) {
        return <img src={animatedUrl} alt={char} className={`inline-block object-contain ${className}`} />;
    }

    const body = bodies?.[char];

    if (!body) {
        return <span>{char}</span>;
    }

    return (
        <svg
            viewBox="0 0 32 32"
            className={`inline-block ${className}`}
            dangerouslySetInnerHTML={{ __html: body }}
        />
    );
}
