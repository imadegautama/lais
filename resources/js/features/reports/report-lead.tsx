import { usePage } from '@inertiajs/react';
import {
    CircleAlert,
    CircleCheck,
    Info,
    MessageCircleQuestion,
    TrendingDown,
    type LucideIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useAssistant } from '@/components/app/assistant/assistant-provider';
import { reportQuestions } from '@/components/app/assistant/report-questions';
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
                'flex flex-col gap-4 rounded-xl border p-4 shadow-card md:p-5 print:hidden',
                surface,
            )}
        >
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between lg:gap-8">
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
            </div>
            <AskAboutReport />
        </section>
    );
}

/**
 * "Yuk tanya AI" under the conclusion: the questions this report raises,
 * answered from its own period (FormAssistant reads the open report).
 */
function AskAboutReport() {
    const { ask } = useAssistant();
    const { component } = usePage();

    return (
        <div className="flex flex-col gap-2 border-t border-foreground/10 pt-3 sm:flex-row sm:items-center sm:gap-3">
            <p className="flex shrink-0 items-center gap-2 text-sm font-medium">
                <MessageCircleQuestion
                    className="size-4 text-brand-strong"
                    aria-hidden
                />
                Bingung membaca laporan ini? Yuk tanya AI:
            </p>
            <div className="flex flex-wrap gap-2">
                {reportQuestions(component).map((question) => (
                    <button
                        key={question}
                        type="button"
                        onClick={() => ask(question)}
                        className="min-h-9 rounded-full border bg-card px-3 text-sm shadow-button-soft hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none pointer-coarse:min-h-11"
                    >
                        {question}
                    </button>
                ))}
            </div>
        </div>
    );
}
