import { Minus, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type QtyStepperProps = {
    /** Names the item, e.g. "Nasi Goreng", for the button labels. */
    label: string;
    value: number;
    onChange: (value: number) => void;
    min?: number;
    max?: number;
    /** 1 for portions, 0.1 for kg or liters. */
    step?: number;
    unit?: string;
    className?: string;
};

export function QtyStepper({
    label,
    value,
    onChange,
    min = 0,
    max = 9999,
    step = 1,
    unit,
    className,
}: QtyStepperProps) {
    const decimals = step < 1 ? 1 : 0;
    const clamp = (next: number) =>
        Math.min(max, Math.max(min, Number(next.toFixed(decimals))));

    return (
        <div
            role="group"
            aria-label={`Jumlah ${label}`}
            className={cn('inline-flex items-center gap-1', className)}
        >
            <Button
                type="button"
                variant="outline"
                size="icon"
                aria-label={`Kurangi ${label}`}
                disabled={value <= min}
                onClick={() => onChange(clamp(value - step))}
            >
                <Minus aria-hidden />
            </Button>
            <output
                aria-live="polite"
                className="num min-w-12 text-center text-base font-semibold"
            >
                {new Intl.NumberFormat('id-ID').format(value)}
                {unit && (
                    <span className="ml-1 text-xs font-medium text-muted-foreground">
                        {unit}
                    </span>
                )}
            </output>
            <Button
                type="button"
                variant="outline"
                size="icon"
                aria-label={`Tambah ${label}`}
                disabled={value >= max}
                onClick={() => onChange(clamp(value + step))}
            >
                <Plus aria-hidden />
            </Button>
        </div>
    );
}
