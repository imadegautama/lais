import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            /** Orders from the shop waiting for Terima or Tolak. */
            pendingOrders: number;
            /** APP_TIMEZONE and its label ("WITA"); see setTimeZone in lib/format. */
            timeZone: { name: string; label: string };
            [key: string]: unknown;
        };
    }
}
