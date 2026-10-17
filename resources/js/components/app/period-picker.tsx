import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { Period, PeriodKey } from '@/types/domain';

type PeriodPickerProps = {
    periods: Array<Pick<Period, 'key' | 'label'>>;
    value: PeriodKey;
    onChange: (key: PeriodKey) => void;
    disabled?: boolean;
};

export function PeriodPicker({
    periods,
    value,
    onChange,
    disabled = false,
}: PeriodPickerProps) {
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
                    onChange(next as PeriodKey);
                }
            }}
            className="bg-card"
        >
            {periods.map((period) => (
                <ToggleGroupItem
                    key={period.key}
                    value={period.key}
                    className="px-3 data-[state=on]:bg-accent data-[state=on]:font-semibold data-[state=on]:text-accent-foreground"
                >
                    {period.label}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
