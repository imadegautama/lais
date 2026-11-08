import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/** A field with a quiet icon at its start (auth pages): the input gets room for it. */
export function InputIcon({
    icon: Icon,
    children,
}: {
    icon: LucideIcon;
    children: ReactNode;
}) {
    return (
        <div className="relative [&_input]:pl-9">
            <Icon
                aria-hidden
                className="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-muted-foreground"
            />
            {children}
        </div>
    );
}
