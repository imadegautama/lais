import type { LucideIcon } from 'lucide-react';
import { useId, type ReactNode } from 'react';
import { cn } from '@/lib/utils';

type SectionCardProps = {
    /** Name the job or the question the section answers, not "Overview". */
    title: string;
    description?: ReactNode;
    icon?: LucideIcon;
    action?: ReactNode;
    /** "focus" marks the one section a screen is built around. */
    tone?: 'default' | 'focus';
    busy?: boolean;
    className?: string;
    contentClassName?: string;
    children: ReactNode;
};

export function SectionCard({
    title,
    description,
    icon: Icon,
    action,
    tone = 'default',
    busy = false,
    className,
    contentClassName,
    children,
}: SectionCardProps) {
    const titleId = useId();

    return (
        <section
            aria-labelledby={titleId}
            aria-busy={busy || undefined}
            className={cn(
                'flex min-w-0 flex-col rounded-xl border bg-card text-card-foreground shadow-card',
                tone === 'focus' && 'border-brand-soft',
                className,
            )}
        >
            <header
                className={cn(
                    'flex items-start gap-3 px-4 pt-4 md:px-5 md:pt-5',
                    tone === 'focus' &&
                        'rounded-t-xl border-b border-brand-soft bg-accent pb-4 md:pb-4',
                )}
            >
                {Icon && <IconChip icon={Icon} tone={tone} />}
                <div className="min-w-0 flex-1">
                    <h2
                        id={titleId}
                        className="text-base leading-snug font-semibold"
                    >
                        {title}
                    </h2>
                    {description && (
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {action && <div className="shrink-0">{action}</div>}
            </header>
            <div
                className={cn(
                    'flex-1 px-4 pt-4 pb-4 md:px-5 md:pb-5',
                    contentClassName,
                )}
            >
                {children}
            </div>
        </section>
    );
}

export function IconChip({
    icon: Icon,
    tone = 'default',
}: {
    icon: LucideIcon;
    tone?: 'default' | 'focus';
}) {
    return (
        <span
            className={cn(
                'flex size-9 shrink-0 items-center justify-center rounded-lg border bg-card',
                tone === 'focus'
                    ? 'border-brand-soft text-brand-strong'
                    : 'text-muted-foreground',
            )}
        >
            <Icon className="size-[18px]" aria-hidden />
        </span>
    );
}
