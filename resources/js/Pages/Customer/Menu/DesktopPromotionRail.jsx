import { useEffect, useMemo, useRef, useState } from 'react';
import { recordPromotionImpression } from './PromotionCarousel';

const SLOT_COUNT = 4;
const ROTATION_INTERVAL_MS = 6000;

export default function DesktopPromotionRail({ promotions, side, startIndex = 0 }) {
    const railRef = useRef(null);
    const recordedIdsRef = useRef(new Set());
    const [rotationOffset, setRotationOffset] = useState(0);

    const visiblePromotions = useMemo(() => {
        if (promotions.length === 0) return [];

        return Array.from(
            { length: Math.min(SLOT_COUNT, promotions.length) },
            (_, slotIndex) => promotions[(startIndex + rotationOffset + slotIndex) % promotions.length],
        );
    }, [promotions, rotationOffset, startIndex]);

    useEffect(() => {
        setRotationOffset(0);
        recordedIdsRef.current.clear();
    }, [promotions]);

    useEffect(() => {
        if (promotions.length <= SLOT_COUNT || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return undefined;
        }

        const timer = window.setInterval(() => {
            setRotationOffset((current) => (current + SLOT_COUNT) % promotions.length);
        }, ROTATION_INTERVAL_MS);

        return () => window.clearInterval(timer);
    }, [promotions.length]);

    useEffect(() => {
        const rail = railRef.current;
        if (!rail || visiblePromotions.length === 0) return undefined;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting || entry.intersectionRatio < 0.55) return;

                const promotion = visiblePromotions.find((item) => item.id === Number(entry.target.dataset.promotionId));
                if (!promotion || recordedIdsRef.current.has(promotion.id)) return;

                recordedIdsRef.current.add(promotion.id);
                recordPromotionImpression(promotion);
                observer.unobserve(entry.target);
            });
        }, { threshold: [0.55] });

        rail.querySelectorAll('[data-promotion-id]').forEach((element) => observer.observe(element));

        return () => observer.disconnect();
    }, [visiblePromotions]);

    if (promotions.length === 0) return <div className="hidden lg:block" aria-hidden="true" />;

    return (
        <aside
            ref={railRef}
            aria-label={`${side} promotions`}
            className="no-scrollbar sticky top-20 hidden max-h-[calc(100vh-6rem)] flex-col gap-3 overflow-y-auto lg:mt-14 lg:flex"
        >
            {visiblePromotions.map((promotion, slotIndex) => {
                const image = (
                    <div
                        key={promotion.id}
                        className="promotion-ad-fade h-full w-full overflow-hidden"
                    >
                        <img
                            src={promotion.image_url}
                            alt={`Promotion ${promotion.code}`}
                            className="block h-full w-full object-cover object-center"
                            loading="lazy"
                            draggable="false"
                        />
                    </div>
                );

                return (
                    <article
                        key={`${side}-${slotIndex}`}
                        data-promotion-id={promotion.id}
                        className="h-44 overflow-hidden rounded-xl border border-[#D8CABB]"
                    >
                        {promotion.has_cta ? (
                            <a
                                href={promotion.click_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label={`Open promotion ${promotion.code}`}
                                className="block h-full rounded-xl focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8A3330]/30"
                            >
                                {image}
                            </a>
                        ) : image}
                    </article>
                );
            })}
        </aside>
    );
}
