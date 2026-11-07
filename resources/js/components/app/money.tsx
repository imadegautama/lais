import { cn } from '@/lib/utils';
import type { MoneyDirection } from '@/types/domain';

const MINUS = '−';

const sizes = {
    sm: 'text-sm font-medium',
    md: 'text-base font-semibold',
    lg: 'text-xl font-semibold',
    xl: 'text-[28px] leading-none font-bold tracking-tight md:text-[32px]',
} as const;

type MoneyProps = {
    value: number;
    /** "in" shows a green +, "out" a −. Omit for balances. */
    direction?: MoneyDirection;
    size?: keyof typeof sizes;
    className?: string;
};

/**
 * Rupiah with a smaller "Rp" prefix: the typographic voice for every
 * amount in the app (DESIGN.md, "Suara tipografi angka").
 */
export function Money({
    value,
    direction,
    size = 'md',
    className,
}: MoneyProps) {
    const sign =
        direction === 'in'
            ? '+'
            : direction === 'out' || value < 0
              ? MINUS
              : '';
    const digits = new Intl.NumberFormat('id-ID').format(
        Math.abs(Math.round(value)),
    );

    return (
        <span
            className={cn(
                'num inline-flex items-baseline whitespace-nowrap',
                sizes[size],
                direction === 'in' && 'text-success',
                className,
            )}
        >
            {sign}
            <span className="mr-px text-[0.8em] font-semibold">Rp</span>
            {digits}
        </span>
    );
}
