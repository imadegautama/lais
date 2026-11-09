import { WifiOff } from 'lucide-react';
import { useSyncExternalStore } from 'react';

const subscribe = (listener: () => void) => {
    window.addEventListener('online', listener);
    window.addEventListener('offline', listener);

    return () => {
        window.removeEventListener('online', listener);
        window.removeEventListener('offline', listener);
    };
};

/**
 * Says so when the phone loses its connection. A form keeps what was typed
 * when saving fails, so the owner only has to press Simpan again.
 */
export function OfflineBanner() {
    const online = useSyncExternalStore(
        subscribe,
        () => navigator.onLine,
        () => true,
    );

    if (online) {
        return null;
    }

    return (
        <p
            role="status"
            className="flex items-center gap-2 border-b border-warning/25 bg-warning-soft px-4 py-2 text-sm md:px-6 print:hidden"
        >
            <WifiOff className="size-4 shrink-0 text-warning" aria-hidden />
            Kamu sedang offline. Isian di form tidak hilang; simpan lagi setelah
            online.
        </p>
    );
}
