import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Identity motif (Usulan, DESIGN.md): a receipt-shaped surface with a
 * torn bottom edge, used only where the content is proof of a
 * transaction (nota, confirmation preview, WA message preview).
 */
export function ReceiptCard({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'receipt-edge rounded-t-xl bg-card px-5 pt-5 text-card-foreground',
                className,
            )}
        >
            {children}
        </div>
    );
}
