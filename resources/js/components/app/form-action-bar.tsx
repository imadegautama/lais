import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Save and cancel stay in reach while a long form scrolls. Sticks to the
 * bottom of the screen, just above the phone bottom nav (DESIGN.md §6).
 * Place it as the last child of the page's padded container.
 */
export function FormActionBar({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'sticky bottom-[calc(4rem+1px+env(safe-area-inset-bottom))] z-20 -mx-4 -mb-4 flex flex-wrap items-center gap-2 border-t bg-card px-4 py-3 shadow-bar md:-mx-6 md:-mb-6 md:px-6 lg:bottom-0',
                className,
            )}
        >
            {children}
        </div>
    );
}
