import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/** Label above, control, then the error or a hint below (DESIGN.md "Form"). */
export function Field({
    label,
    htmlFor,
    error,
    hint,
    className,
    children,
}: {
    label: string;
    htmlFor: string;
    error?: string;
    hint?: ReactNode;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {error ? (
                <InputError message={error} />
            ) : (
                hint && <p className="text-xs text-muted-foreground">{hint}</p>
            )}
        </div>
    );
}
