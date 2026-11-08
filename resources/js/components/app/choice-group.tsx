import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';

/** One choice from a short, fixed list (payment method, kind). */
export function ChoiceGroup<V extends string>({
    label,
    labelledBy,
    value,
    options,
    onChange,
    wrap = false,
    className,
}: {
    label?: string;
    labelledBy?: string;
    value: V | '';
    options: Array<{ value: V; label: string }>;
    onChange: (value: V) => void;
    /** Many options wrap as chips; a few fill the row as equal buttons. */
    wrap?: boolean;
    className?: string;
}) {
    return (
        <ToggleGroup
            type="single"
            variant="outline"
            value={value}
            onValueChange={(next) => next && onChange(next as V)}
            aria-label={label}
            aria-labelledby={labelledBy}
            className={cn(
                'w-full gap-1.5',
                wrap
                    ? 'flex-wrap justify-start'
                    : 'grid grid-cols-2 sm:auto-cols-fr sm:grid-flow-col',
                className,
            )}
        >
            {options.map((option) => (
                <ToggleGroupItem
                    key={option.value}
                    value={option.value}
                    className="h-auto min-h-9 rounded-md! border-l! bg-card px-3 py-1.5 text-sm whitespace-normal data-[state=on]:border-primary data-[state=on]:bg-accent data-[state=on]:font-semibold data-[state=on]:text-accent-foreground pointer-coarse:min-h-11"
                >
                    {option.label}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
