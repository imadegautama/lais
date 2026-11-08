import type { RefObject } from 'react';
import { useEffect, useState } from 'react';

/**
 * Which `[data-step]` is being read: the one crossing the middle of the
 * screen. -1 before the first, and the last index plus one after the end.
 */
export function useActiveStep(root: RefObject<HTMLElement | null>): number {
    const [active, setActive] = useState(-1);

    useEffect(() => {
        if (root.current === null) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        setActive(
                            Number((entry.target as HTMLElement).dataset.step),
                        );
                    }
                }
            },
            { rootMargin: '-45% 0px -45% 0px' },
        );

        root.current
            .querySelectorAll<HTMLElement>('[data-step]')
            .forEach((step) => observer.observe(step));

        return () => observer.disconnect();
    }, [root]);

    return active;
}
