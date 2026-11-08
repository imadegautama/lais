import Lenis from 'lenis';
import { useEffect } from 'react';

/**
 * Softens the mouse wheel on the landing page, and only there. Touch stays
 * native, and Lenis honours prefers-reduced-motion by itself.
 */
export function useSmoothScroll(): void {
    useEffect(() => {
        const lenis = new Lenis({
            autoRaf: true,
            anchors: true,
            stopInertiaOnNavigate: true,
        });

        return () => lenis.destroy();
    }, []);
}
