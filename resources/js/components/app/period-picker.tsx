import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';

type PeriodPickerProps<K extends string> = {
    periods: Array<{ key: K; label: string }>;
    value: K;
    onChange: (key: K) => void;
    disabled?: boolean;
    /** Stretch over the full width with tighter items, for four or more periods on a phone. */
    fill?: boolean;
};

/** Single choice of period. Generic so a list can add its own "Semua". */
export function PeriodPicker<K extends string>({
    periods,
    value,
    onChange,
    disabled = false,
    fill = false,
}: PeriodPickerProps<K>) {
    return (
        <ToggleGroup
            type="single"
            variant="outline"
            value={value}
            disabled={disabled}
            aria-label="Pilih periode"
            onValueChange={(next) => {
                // Radix clears the value when the active item is clicked again.
                if (next) {
                    onChange(next as K);
                }
            }}
            className={cn('bg-card', fill && 'w-full sm:w-fit')}
        >
            {periods.map((period) => (
                <ToggleGroupItem
                    key={period.key}
                    value={period.key}
                    className={cn(
                        'px-3 data-[state=on]:bg-accent data-[state=on]:font-semibold data-[state=on]:text-accent-foreground',
                        fill &&
                            'flex-1 px-1.5 text-xs sm:flex-none sm:px-3 sm:text-sm',
                    )}
                >
                    {period.label}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
