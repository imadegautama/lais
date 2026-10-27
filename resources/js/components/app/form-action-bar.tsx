import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Save and cancel stay in reach while a long form scrolls. Sticks to the
 * bottom of the screen, just above the phone bottom nav (DESIGN.md §6).
 * Make it the last child of a `flex-1 flex-col` form inside a `flex-1`
 * page container: mt-auto then keeps it at the bottom when the form is short.
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
            // app.css lifts the floating "Tanya AI" button above this bar.
            data-slot="form-action-bar"
            className={cn(
                'sticky bottom-[calc(4rem+1px+env(safe-area-inset-bottom))] z-20 -mx-4 mt-auto -mb-4 flex flex-wrap items-center gap-2 border-t bg-card px-4 py-3 shadow-bar md:-mx-6 md:-mb-6 md:px-6 lg:bottom-0',
                className,
            )}
        >
            {children}
        </div>
    );
}
