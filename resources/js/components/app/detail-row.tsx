import type { ReactNode } from 'react';

/** One label and value inside a <dl>. */
export function DetailRow({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex items-baseline justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right font-medium">{children}</dd>
        </div>
    );
}
