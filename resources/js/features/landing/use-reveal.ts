import type { RefObject } from 'react';
import { useEffect } from 'react';

/** The demo plays once this much of it is in sight, so its start is seen. */
const PLAY_SHARE = 0.3;

/**
 * Holds back each `[data-reveal]` the reader has not reached, and lets it go
 * on arrival (landing.css). Only this hook hides anything, so without
 * JavaScript the page is simply complete.
 */
export function useReveal(root: RefObject<HTMLElement | null>): void {
    useEffect(() => {
        if (
            root.current === null ||
            window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    const share =
                        (entry.target as HTMLElement).dataset.reveal === 'play'
                            ? PLAY_SHARE
                            : 0;

                    if (
                        entry.isIntersecting &&
                        entry.intersectionRatio >= share
                    ) {
                        entry.target.removeAttribute('data-waiting');
                        observer.unobserve(entry.target);
                    }
                }
            },
            // In pixels: a share of a tall screen would never reach the last elements.
            { rootMargin: '0px 0px -80px 0px', threshold: [0, PLAY_SHARE] },
        );

        root.current
            .querySelectorAll<HTMLElement>('[data-reveal]')
            .forEach((element) => {
                const { top, width } = element.getBoundingClientRect();

                // Hidden or read-out-only on this screen: nothing to show.
                if (width <= 1) {
                    return;
                }

                // Anything in sight stays put, or it would fade out on load.
                // The demo is the exception: its play should not be missed.
                const fold = element.dataset.reveal === 'play' ? 0.8 : 1;

                if (top > window.innerHeight * fold) {
                    element.setAttribute('data-waiting', '');
                    observer.observe(element);
                }
            });

        return () => observer.disconnect();
    }, [root]);
}
