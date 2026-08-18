import { useEffect, useRef, useState } from 'react';

// The active category is the last section whose top has already scrolled
// past this line (just below the sticky header + chip bar) — the standard
// scrollspy rule, and unlike IntersectionObserver it stays correct in both
// scroll directions and never flickers between two categories when one
// section is short.
const ACTIVE_LINE = 140;

export default function useCategoryScrollspy() {
    const [selectedCategory, setSelectedCategory] = useState('all');
    const menuTopRef = useRef(null);
    const chipBarRef = useRef(null);

    useEffect(() => {
        const sections = [...document.querySelectorAll('[data-category-id]')];
        if (!sections.length) return undefined;

        let ticking = false;

        const updateActiveCategory = () => {
            let current = sections[0];

            for (const section of sections) {
                if (section.getBoundingClientRect().top - ACTIVE_LINE <= 0) {
                    current = section;
                } else {
                    break;
                }
            }

            setSelectedCategory(Number(current.dataset.categoryId));
            ticking = false;
        };

        updateActiveCategory();

        const onScroll = () => {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(updateActiveCategory);
        };

        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    useEffect(() => {
        // Scroll only the chip strip itself (never scrollIntoView here — it
        // drags the whole page's scroll position too when the strip is
        // inside a position:sticky ancestor).
        const bar = chipBarRef.current;
        const chip = document.getElementById(`chip-${selectedCategory}`);
        if (!bar || !chip) return;

        bar.scrollTo({
            left: chip.offsetLeft - bar.clientWidth / 2 + chip.clientWidth / 2,
            behavior: 'smooth',
        });
    }, [selectedCategory]);

    const selectCategory = (id) => {
        setSelectedCategory(id);
        const target = id === 'all' ? menuTopRef.current : document.getElementById(`category-${id}`);
        target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    return { selectedCategory, selectCategory, chipBarRef, menuTopRef };
}
