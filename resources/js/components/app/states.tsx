import { CircleAlert, RotateCcw, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

type EmptyStateProps = {
    icon?: LucideIcon;
    /** Why it is empty, in the owner's words. */
    title: string;
    /** What fills it. */
    description: ReactNode;
    action?: ReactNode;
    className?: string;
};

export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    className,
}: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-start gap-2 rounded-lg border border-dashed px-4 py-5',
                className,
            )}
        >
            {Icon && (
                <Icon className="size-5 text-muted-foreground" aria-hidden />
            )}
            <p className="text-sm font-semibold">{title}</p>
            <p className="text-sm text-muted-foreground">{description}</p>
            {action && <div className="mt-1">{action}</div>}
        </div>
    );
}

type ErrorStateProps = {
    /** What failed. */
    title: string;
    /** What to do next. */
    description: ReactNode;
    onRetry?: () => void;
    className?: string;
};

export function ErrorState({
    title,
    description,
    onRetry,
    className,
}: ErrorStateProps) {
    return (
        <div
            role="alert"
            className={cn(
                'flex flex-col items-start gap-2 rounded-lg bg-destructive-soft px-4 py-4',
                className,
            )}
        >
            <p className="flex items-center gap-2 text-sm font-semibold text-destructive">
                <CircleAlert className="size-4" aria-hidden />
                {title}
            </p>
            <p className="text-sm text-foreground">{description}</p>
            {onRetry && (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={onRetry}
                    className="mt-1 bg-card"
                >
                    <RotateCcw aria-hidden />
                    Coba lagi
                </Button>
            )}
        </div>
    );
}

/** Loading placeholder that says what is loading, for screen readers too. */
export function LoadingBlock({
    label,
    rows = 3,
    className,
}: {
    label: string;
    rows?: number;
    className?: string;
}) {
    return (
        <div role="status" className={cn('flex flex-col gap-3', className)}>
            <span className="sr-only">{label}</span>
            {Array.from({ length: rows }, (_, i) => (
                <Skeleton
                    key={i}
                    className={cn('h-10 w-full', i === rows - 1 && 'w-2/3')}
                />
            ))}
        </div>
    );
}
