import type { ReactNode } from 'react';
import { Money } from '@/components/app/money';
import { TermHint } from '@/features/reports/term-hint';
import type { Term } from '@/features/reports/vocabulary';
import { cn } from '@/lib/utils';

export type StatementRow = {
    label: string;
    /** Signed: what the row adds to or takes from the line below it. */
    amount: number;
    term?: Term;
    /** line: a figure; detail: indented under the line above; subtotal and total close a group. */
    level?: 'line' | 'detail' | 'subtotal' | 'total';
    /** Shown after the amount, e.g. a "Rugi" badge. */
    note?: ReactNode;
};

/** A stepped statement (laba rugi, neraca, perubahan modal): label left, amount right. */
export function Statement({ rows }: { rows: StatementRow[] }) {
    return (
        <dl className="flex flex-col">
            {rows.map((row) => {
                const level = row.level ?? 'line';

                return (
                    <div
                        key={row.label}
                        className={cn(
                            'flex items-center justify-between gap-3 py-2',
                            level === 'detail' &&
                                'py-1.5 pl-4 text-sm text-muted-foreground',
                            level === 'subtotal' && 'border-t font-semibold',
                            level === 'total' &&
                                'mt-1 border-t-2 border-foreground/80 pt-3 font-semibold',
                        )}
                    >
                        <dt className="flex min-w-0 items-center gap-1">
                            <span>{row.label}</span>
                            {row.term && (
                                <TermHint term={row.term} label={row.label} />
                            )}
                        </dt>
                        <dd className="flex shrink-0 items-center gap-2">
                            {row.note}
                            <Money
                                value={row.amount}
                                size={
                                    level === 'total'
                                        ? 'lg'
                                        : level === 'detail'
                                          ? 'sm'
                                          : 'md'
                                }
                                className={cn(
                                    level === 'detail' &&
                                        'text-muted-foreground',
                                )}
                            />
                        </dd>
                    </div>
                );
            })}
        </dl>
    );
}
