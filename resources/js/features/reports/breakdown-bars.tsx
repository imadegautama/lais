import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { Money } from '@/components/app/money';
import { cn } from '@/lib/utils';

export type BreakdownRow = {
    label: string;
    amount: number;
    /** Opens the detail behind the row. */
    href?: string;
    /** A line under the label, such as how much was sold. */
    hint?: ReactNode;
    /** Shown beside the amount, such as a margin label. */
    note?: ReactNode;
};

/**
 * How parts compare, as thin bars sized against the largest. The amount is
 * always written, so the bar only helps the eye; the largest is the one
 * highlighted, because that is usually the answer.
 */
export function BreakdownBars({
    rows,
    highlightFirst = true,
    label,
}: {
    rows: BreakdownRow[];
    highlightFirst?: boolean;
    /** Names the list for screen readers. */
    label: string;
}) {
    const largest = Math.max(...rows.map((row) => Math.abs(row.amount)), 1);
    const top = rows.reduce(
        (best, row, index) =>
            Math.abs(row.amount) > Math.abs(rows[best].amount) ? index : best,
        0,
    );

    return (
        <ul aria-label={label} className="flex flex-col gap-3">
            {rows.map((row, index) => {
                const content = (
                    <>
                        <span className="flex items-baseline justify-between gap-3">
                            <span className="min-w-0 text-sm font-medium">
                                {row.label}
                                {row.hint && (
                                    <span className="block text-xs font-normal text-muted-foreground">
                                        {row.hint}
                                    </span>
                                )}
                            </span>
                            <span className="flex shrink-0 items-center gap-2">
                                {row.note}
                                <Money value={row.amount} size="sm" />
                                {row.href && (
                                    <ChevronRight
                                        className="size-4 text-muted-foreground"
                                        aria-hidden
                                    />
                                )}
                            </span>
                        </span>
                        <span
                            aria-hidden
                            className="mt-1.5 block h-2 overflow-hidden rounded-full bg-secondary"
                        >
                            <span
                                className={cn(
                                    'block h-full rounded-full',
                                    highlightFirst && index === top
                                        ? 'bg-chart-1'
                                        : 'bg-chart-2',
                                )}
                                style={{
                                    width: `${Math.max((Math.abs(row.amount) / largest) * 100, row.amount === 0 ? 0 : 1.5)}%`,
                                }}
                            />
                        </span>
                    </>
                );

                return (
                    <li key={row.label}>
                        {row.href ? (
                            <Link
                                href={row.href}
                                className="-mx-2 block rounded-lg px-2 py-1 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none pointer-coarse:py-2"
                            >
                                {content}
                            </Link>
                        ) : (
                            <div className="py-1">{content}</div>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
