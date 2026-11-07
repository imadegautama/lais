import { useEffect, useState } from 'react';
import { flushSync } from 'react-dom';

/**
 * True while the browser prints, so a paged table hands paper every row.
 * Synchronous, because the browser takes the page right after `beforeprint`.
 */
export function usePrinting(): boolean {
    const [printing, setPrinting] = useState(false);

    useEffect(() => {
        const start = () => flushSync(() => setPrinting(true));
        const stop = () => setPrinting(false);

        window.addEventListener('beforeprint', start);
        window.addEventListener('afterprint', stop);

        return () => {
            window.removeEventListener('beforeprint', start);
            window.removeEventListener('afterprint', stop);
        };
    }, []);

    return printing;
}
