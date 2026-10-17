import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type PageHeaderProps = {
    title: string;
    description?: ReactNode;
    /** Small labels next to the title, e.g. <DemoDataBadge />. */
    meta?: ReactNode;
    actions?: ReactNode;
    className?: string;
};

export function PageHeader({
    title,
    description,
    meta,
    actions,
    className,
}: PageHeaderProps) {
    return (
        <div
            className={cn(
                'flex flex-col gap-3 md:flex-row md:items-end md:justify-between',
                className,
            )}
        >
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h1 className="text-xl font-semibold tracking-tight md:text-2xl">
                        {title}
                    </h1>
                    {meta}
                </div>
                {description && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
