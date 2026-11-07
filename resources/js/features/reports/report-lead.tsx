import {
    CircleAlert,
    CircleCheck,
    Info,
    TrendingDown,
    type LucideIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { ReportStory } from '@/types/domain';

const tones: Record<
    ReportStory['tone'],
    { icon: LucideIcon; surface: string; mark: string }
> = {
    good: {
        icon: CircleCheck,
        surface: 'border-success/20 bg-success-soft',
        mark: 'text-success',
    },
    warning: {
        icon: CircleAlert,
        surface: 'border-warning/25 bg-warning-soft',
        mark: 'text-warning',
    },
    bad: {
        icon: TrendingDown,
        surface: 'border-destructive/20 bg-destructive-soft',
        mark: 'text-destructive',
    },
    neutral: {
        icon: Info,
        surface: 'border-border bg-card',
        mark: 'text-muted-foreground',
    },
};

/**
 * What the report says, before any figure: the owner reads the conclusion
 * first and opens the numbers only to check it. On paper the statement alone
 * stands, so this stays off the page.
 */
export function ReportLead({
    story,
    children,
}: {
    story: ReportStory;
    /** Figures that belong beside the conclusion. */
    children?: ReactNode;
}) {
    const { icon: Icon, surface, mark } = tones[story.tone];

    return (
        <section
            aria-label="Kesimpulan laporan"
            className={cn(
                'flex flex-col gap-4 rounded-xl border p-4 shadow-card md:p-5 lg:flex-row lg:items-center lg:justify-between lg:gap-8 print:hidden',
                surface,
            )}
        >
            <div className="flex min-w-0 items-start gap-3">
                <Icon
                    className={cn('mt-1 size-5 shrink-0', mark)}
                    aria-hidden
                />
                <div className="min-w-0">
                    <p className="text-lg font-semibold text-balance md:text-xl">
                        {story.headline}
                    </p>
                    <p className="mt-1 max-w-prose text-sm text-foreground/80">
                        {story.detail}
                    </p>
                </div>
            </div>
            {children}
        </section>
    );
}
