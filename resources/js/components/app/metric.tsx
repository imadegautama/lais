import { Minus, TrendingDown, TrendingUp } from 'lucide-react';
import type { ReactNode } from 'react';
import { Money } from '@/components/app/money';
import { formatPercent, formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';

type DeltaProps = {
    value: number;
    previous: number | null;
    /** Named comparison such as "vs kemarin". No label, no delta. */
    comparisonLabel: string | null;
    /** Which direction is good news. Personal draws use "neutral". */
    goodWhen?: 'up' | 'down' | 'neutral';
    className?: string;
};

/**
 * A change against a real, named period. Renders nothing when there is
 * no earlier data, so a trend is never implied without one.
 */
export function DeltaBadge({
    value,
    previous,
    comparisonLabel,
    goodWhen = 'up',
    className,
}: DeltaProps) {
    if (previous === null || comparisonLabel === null) {
        return null;
    }

    const diff = value - previous;
    const pct = previous === 0 ? null : (diff / Math.abs(previous)) * 100;
    const flat = diff === 0 || (pct !== null && Math.abs(pct) < 0.5);
    const up = diff > 0;

    const tone = flat
        ? 'neutral'
        : goodWhen === 'neutral'
          ? 'neutral'
          : (goodWhen === 'up') === up
            ? 'good'
            : 'bad';

    const Icon = flat ? Minus : up ? TrendingUp : TrendingDown;
    const word = flat ? 'Sama' : up ? 'Naik' : 'Turun';
    const amount = flat
        ? ''
        : pct === null
          ? formatRupiah(Math.abs(diff))
          : formatPercent(Math.abs(pct), Math.abs(pct) < 10 ? 1 : 0);

    return (
        <span
            className={cn(
                'inline-flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs',
                className,
            )}
        >
            <span
                className={cn(
                    'inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 font-semibold',
                    tone === 'good' && 'bg-success-soft text-success',
                    tone === 'bad' && 'bg-destructive-soft text-destructive',
                    tone === 'neutral' && 'bg-secondary text-muted-foreground',
                )}
            >
                <Icon className="size-3.5" aria-hidden />
                {word} {amount}
            </span>
            <span className="text-muted-foreground">{comparisonLabel}</span>
        </span>
    );
}

type MetricFocusProps = {
    label: string;
    value: number;
    delta?: ReactNode;
    hint?: ReactNode;
};

/** The one number a screen is built around. */
export function MetricFocus({ label, value, delta, hint }: MetricFocusProps) {
    return (
        <div className="flex flex-col gap-2">
            <span className="text-sm font-medium text-muted-foreground">
                {label}
            </span>
            <Money value={value} size="xl" />
            {delta}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
        </div>
    );
}

type MetricInlineProps = {
    label: string;
    value: ReactNode;
    detail?: ReactNode;
    delta?: ReactNode;
};

/** A supporting number: one row, deliberately quieter than MetricFocus. */
export function MetricInline({
    label,
    value,
    detail,
    delta,
}: MetricInlineProps) {
    return (
        <div className="flex flex-col gap-1 py-3 first:pt-0 last:pb-0">
            <div className="flex items-baseline justify-between gap-3">
                <span className="text-sm text-muted-foreground">{label}</span>
                {value}
            </div>
            {(detail || delta) && (
                <div className="flex flex-wrap items-center justify-between gap-2">
                    {detail && (
                        <span className="text-xs text-muted-foreground">
                            {detail}
                        </span>
                    )}
                    {delta}
                </div>
            )}
        </div>
    );
}
