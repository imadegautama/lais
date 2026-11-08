import { Link } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Money } from '@/components/app/money';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { TermHint } from '@/components/app/term-hint';
import type { Term } from '@/components/app/term-hint';
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
    /** The same line one period earlier (SAK EMKM par. 3.8). */
    previous?: number;
    /** Where the line comes from, usually its Buku besar. */
    href?: string;
    /** What the line is made of; the owner opens it, paper always shows it. */
    details?: StatementRow[];
    /** The note that explains the line (SAK EMKM par. 6.4). */
    reference?: { label: string; href: string };
};

/** Pairs each row with its figure one period earlier, line by line. */
export function withPrevious(
    rows: StatementRow[],
    earlier: StatementRow[],
): StatementRow[] {
    return rows.map((row, index) => ({
        ...row,
        previous: earlier[index]?.amount ?? 0,
        details:
            row.details &&
            withPrevious(row.details, earlier[index]?.details ?? []),
    }));
}

/**
 * "+12%", "−99%", measured on the size of the line: a cost shown as −Rp300 that
 * was −Rp100 grew, so it reads "+200%". A loss that became a profit has no
 * honest percentage, and a line with nothing to compare with has none either.
 */
export function changeLabel(amount: number, previous: number): string {
    if (previous === 0) {
        return amount === 0 ? '' : 'baru';
    }

    if (amount * previous < 0) {
        return 'berbalik';
    }

    const percent =
        ((Math.abs(amount) - Math.abs(previous)) / Math.abs(previous)) * 100;
    const size = Math.abs(percent);

    if (size < 0.5) {
        return '0%';
    }

    return `${percent > 0 ? '+' : '−'}${size >= 10 ? Math.round(size) : size.toFixed(1).replace('.', ',')}%`;
}

type Columns = { current: string; previous: string };

function Figures({ row, columns }: { row: StatementRow; columns?: Columns }) {
    const level = row.level ?? 'line';

    return (
        <dd
            className={cn(
                'flex shrink-0 items-center gap-2',
                columns &&
                    'flex-col items-end gap-0 @lg:flex-row @lg:items-center @lg:gap-3 print:flex-row print:items-center print:gap-3',
            )}
        >
            <span
                className={cn(
                    'flex items-center justify-end gap-2',
                    columns && '@lg:w-32 print:w-32',
                )}
            >
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
                        level === 'detail' && 'text-muted-foreground',
                    )}
                />
            </span>
            {columns && (
                <>
                    <span className="flex items-baseline justify-end gap-1 text-xs text-muted-foreground @lg:w-32 @lg:text-sm print:w-32">
                        <span className="sr-only">{columns.previous}</span>
                        <Money
                            value={row.previous ?? 0}
                            size="sm"
                            className="text-muted-foreground"
                        />
                    </span>
                    <span className="num hidden w-16 text-right text-xs text-muted-foreground @2xl:block print:hidden">
                        {changeLabel(row.amount, row.previous ?? 0)}
                    </span>
                </>
            )}
        </dd>
    );
}

function Label({ row }: { row: StatementRow }) {
    return (
        <>
            {row.href ? (
                <Link
                    href={row.href}
                    className="min-w-0 rounded-sm hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none pointer-coarse:-my-3 pointer-coarse:py-3"
                >
                    {row.label}
                    <ChevronRight
                        className="ml-0.5 inline size-3.5 align-[-2px] text-muted-foreground print:hidden"
                        aria-hidden
                    />
                </Link>
            ) : (
                <span className="min-w-0">{row.label}</span>
            )}
            {row.term && <TermHint term={row.term} label={row.label} />}
            {row.reference && (
                <Link
                    href={row.reference.href}
                    className="ml-1 hidden shrink-0 text-xs text-muted-foreground underline underline-offset-2 hover:text-foreground @md:inline print:inline pointer-coarse:-my-3 pointer-coarse:py-3"
                >
                    {row.reference.label}
                </Link>
            )}
        </>
    );
}

const rowStyle = (level: NonNullable<StatementRow['level']>) =>
    cn(
        'flex items-center justify-between gap-3 py-2',
        level === 'detail' && 'py-1.5 pl-6 text-sm text-muted-foreground',
        level === 'subtotal' && 'border-t font-semibold',
        level === 'total' &&
            'mt-1 border-t-2 border-foreground/80 pt-3 font-semibold',
    );

function ExpandableRow({
    row,
    columns,
}: {
    row: StatementRow;
    columns?: Columns;
}) {
    const [open, setOpen] = useState(false);
    const details = (row.details ?? []).filter(
        (detail) => detail.amount !== 0 || (detail.previous ?? 0) !== 0,
    );

    return (
        <Collapsible open={open} onOpenChange={setOpen}>
            <div className={rowStyle(row.level ?? 'line')}>
                <dt className="flex min-w-0 items-center gap-1">
                    <CollapsibleTrigger
                        className="-ml-1 inline-flex size-6 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none print:hidden pointer-coarse:size-11"
                        aria-label={`${open ? 'Tutup' : 'Buka'} rincian ${row.label.toLowerCase()}`}
                    >
                        <ChevronDown
                            className={cn(
                                'size-4 transition-transform motion-reduce:transition-none',
                                !open && '-rotate-90',
                            )}
                            aria-hidden
                        />
                    </CollapsibleTrigger>
                    <Label row={row} />
                </dt>
                <Figures row={row} columns={columns} />
            </div>
            {/* Kept in the page so paper always carries the detail (UI-PAGES C14). */}
            <CollapsibleContent
                forceMount
                className="data-[state=closed]:hidden print:block!"
            >
                {details.map((detail) => (
                    <div key={detail.label} className={rowStyle('detail')}>
                        <dt className="flex min-w-0 items-center gap-1">
                            <Label row={detail} />
                        </dt>
                        <Figures
                            row={{ ...detail, level: 'detail' }}
                            columns={columns}
                        />
                    </div>
                ))}
            </CollapsibleContent>
        </Collapsible>
    );
}

/**
 * A stepped statement (laba rugi, neraca, perubahan modal): label left, amount
 * right. It reads its own width, not the screen's, so the same table works in a
 * wide column and a narrow one: with `columns`, the earlier period sits beside
 * the amount when there is room and under it when there is not.
 */
export function Statement({
    rows,
    columns,
}: {
    rows: StatementRow[];
    columns?: Columns;
}) {
    // A line empty in both periods says nothing; totals always stay.
    const visible = rows.filter(
        (row) =>
            !columns ||
            (row.level ?? 'line') !== 'line' ||
            row.amount !== 0 ||
            (row.previous ?? 0) !== 0,
    );

    return (
        <dl className="@container flex flex-col">
            {columns && (
                <>
                    <p
                        aria-hidden
                        className="pb-1 text-right text-xs font-medium text-muted-foreground @lg:hidden print:hidden"
                    >
                        Angka kecil: {columns.previous}
                    </p>
                    <div
                        aria-hidden
                        className="hidden justify-end gap-3 pb-1 text-xs font-medium text-muted-foreground @lg:flex print:flex"
                    >
                        <span className="w-32 text-right">
                            {columns.current}
                        </span>
                        <span className="w-32 text-right">
                            {columns.previous}
                        </span>
                        <span className="hidden w-16 text-right @2xl:block print:hidden">
                            Perubahan
                        </span>
                    </div>
                </>
            )}
            {visible.map((row) =>
                row.details?.some(
                    (detail) =>
                        detail.amount !== 0 || (detail.previous ?? 0) !== 0,
                ) ? (
                    <ExpandableRow
                        key={row.label}
                        row={row}
                        columns={columns}
                    />
                ) : (
                    <div
                        key={row.label}
                        className={rowStyle(row.level ?? 'line')}
                    >
                        <dt className="flex min-w-0 items-center gap-1">
                            <Label row={row} />
                        </dt>
                        <Figures row={row} columns={columns} />
                    </div>
                ),
            )}
        </dl>
    );
}
