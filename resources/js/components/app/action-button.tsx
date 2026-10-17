import { Link } from '@inertiajs/react';
import type { ComponentProps, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { ActionTarget } from '@/types/domain';

type ActionButtonProps = Omit<ComponentProps<typeof Button>, 'asChild'> & {
    target: ActionTarget;
    children: ReactNode;
};

/**
 * A button whose destination may not be built yet. Unbuilt targets
 * render disabled with a visible "Segera" tag instead of a dead link
 * (antislop R-26). Swap the target to { href } when the page lands.
 */
export function ActionButton({
    target,
    children,
    className,
    ...props
}: ActionButtonProps) {
    if ('href' in target) {
        return (
            <Button asChild className={className} {...props}>
                <Link href={target.href}>{children}</Link>
            </Button>
        );
    }

    return (
        <Button disabled className={cn('gap-2', className)} {...props}>
            {children}
            <SoonTag />
        </Button>
    );
}

export function SoonTag({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                'rounded-sm bg-secondary px-1.5 py-px text-[11px] leading-4 font-semibold text-muted-foreground',
                className,
            )}
        >
            Segera
        </span>
    );
}
