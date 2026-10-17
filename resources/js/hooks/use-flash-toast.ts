import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            const action = data.action;

            toast[data.type](data.message, {
                description: data.description,
                action: action && {
                    label: action.label,
                    onClick: () => router.visit(action.href),
                },
            });
        });
    }, []);
}
