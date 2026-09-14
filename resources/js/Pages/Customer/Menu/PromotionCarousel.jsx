import { useCallback, useEffect, useRef, useState } from 'react';

export function recordPromotionImpression(promotion) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    fetch(promotion.view_url, {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        },
    }).catch(() => {
        // Analytics must never interrupt browsing or ordering.
    });
}

export default function PromotionCarousel({ promotions = [] }) {
    const scrollerRef = useRef(null);
    const recordedIdsRef = useRef(new Set());
    const animationFrameRef = useRef(null);
    const [activeIndex, setActiveIndex] = useState(0);
    const [isInteracting, setIsInteracting] = useState(false);

    const markVisible = useCallback((index) => {
        const promotion = promotions[index];
        if (!promotion || recordedIdsRef.current.has(promotion.id)) return;

        recordedIdsRef.current.add(promotion.id);
        recordPromotionImpression(promotion);
    }, [promotions]);

    useEffect(() => {
        const desktopQuery = window.matchMedia('(min-width: 1024px)');
        const markMobileBanner = () => {
            if (!desktopQuery.matches && promotions.length > 0) markVisible(activeIndex);
        };

        markMobileBanner();
        desktopQuery.addEventListener('change', markMobileBanner);

        return () => desktopQuery.removeEventListener('change', markMobileBanner);
    }, [activeIndex, markVisible, promotions.length]);

    useEffect(() => () => {
        if (animationFrameRef.current) cancelAnimationFrame(animationFrameRef.current);
    }, []);

    useEffect(() => {
        if (
            promotions.length < 2
            || isInteracting
            || window.matchMedia('(min-width: 1024px)').matches
            || window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ) return undefined;

        const timer = window.setTimeout(() => {
            const nextIndex = (activeIndex + 1) % promotions.length;
            const scroller = scrollerRef.current;
            const slide = scroller?.children[nextIndex];

            // scrollLeft on the scroller itself, never slide.scrollIntoView()
            // — scrollIntoView considers the page's own vertical scroll too,
            // and will yank the whole page back up to bring this carousel
            // into view if the visitor has since scrolled down to browse
            // the menu. This must only ever move the carousel horizontally.
            if (scroller && slide) {
                scroller.scrollTo({ left: slide.offsetLeft, behavior: 'smooth' });
            }
            setActiveIndex(nextIndex);
            markVisible(nextIndex);
        }, 6000);

        return () => window.clearTimeout(timer);
    }, [activeIndex, isInteracting, markVisible, promotions.length]);

    if (promotions.length === 0) return null;

    const goTo = (index) => {
        const scroller = scrollerRef.current;
        const slide = scroller?.children[index];
        if (!scroller || !slide) return;

        // Same reasoning as the auto-rotate timer above — scrollLeft only,
        // never scrollIntoView, so tapping a dot never drags the page's
        // own scroll position along with it.
        scroller.scrollTo({ left: slide.offsetLeft, behavior: 'smooth' });
        setActiveIndex(index);
        markVisible(index);
    };

    const updateActiveSlide = () => {
        if (animationFrameRef.current) cancelAnimationFrame(animationFrameRef.current);

        animationFrameRef.current = requestAnimationFrame(() => {
            const scroller = scrollerRef.current;
            if (!scroller) return;

            const slides = Array.from(scroller.children);
            const closestIndex = slides.reduce((bestIndex, slide, index) => (
                Math.abs(slide.offsetLeft - scroller.scrollLeft)
                    < Math.abs(slides[bestIndex].offsetLeft - scroller.scrollLeft)
                    ? index
                    : bestIndex
            ), 0);

            setActiveIndex(closestIndex);
            markVisible(closestIndex);
        });
    };

    return (
        <section aria-label="Promotions" className="mx-auto max-w-5xl pb-3 pt-1 lg:hidden">
            <div
                ref={scrollerRef}
                onScroll={updateActiveSlide}
                onPointerDown={() => setIsInteracting(true)}
                onPointerUp={() => setIsInteracting(false)}
                onPointerCancel={() => setIsInteracting(false)}
                onFocusCapture={() => setIsInteracting(true)}
                onBlurCapture={() => setIsInteracting(false)}
                className={`no-scrollbar flex snap-x snap-mandatory scroll-px-4 gap-3 overflow-x-auto px-4 overscroll-x-contain scroll-smooth ${
                    promotions.length > 1 ? "after:block after:w-5 after:shrink-0 after:content-['']" : ''
                }`}
            >
                {promotions.map((promotion) => {
                    const banner = (
                        <div className="aspect-[4/3] w-full overflow-hidden sm:aspect-[3/1]">
                            <img
                                src={promotion.mobile_image_url}
                                alt={`Promotion ${promotion.code}`}
                                className="block h-full w-full object-cover object-center"
                                loading="eager"
                                draggable="false"
                            />
                        </div>
                    );

                    return (
                        <article
                            key={promotion.id}
                            className={`min-w-0 snap-start overflow-hidden rounded-2xl border border-[#D8CABB] ${
                                promotions.length > 1 ? 'basis-[calc(100%-1.25rem)] shrink-0 sm:basis-full' : 'basis-full shrink-0'
                            }`}
                        >
                            {promotion.has_cta ? (
                                <a
                                    href={promotion.click_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label={`Open promotion ${promotion.code}`}
                                    className="block focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/30"
                                >
                                    {banner}
                                </a>
                            ) : banner}
                        </article>
                    );
                })}
            </div>

            {promotions.length > 1 && (
                <div className="mt-3 flex items-center justify-center gap-2" aria-label="Choose a promotion">
                    {promotions.map((promotion, index) => (
                        <button
                            key={promotion.id}
                            type="button"
                            onClick={() => goTo(index)}
                            aria-label={`Show promotion ${index + 1} of ${promotions.length}`}
                            aria-current={activeIndex === index ? 'true' : undefined}
                            className={`h-2 rounded-full transition-all ${
                                activeIndex === index ? 'w-5 bg-[#8A3330]' : 'w-2 bg-[#D9CCBA] hover:bg-[#BCA99A]'
                            }`}
                        />
                    ))}
                </div>
            )}
        </section>
    );
}
