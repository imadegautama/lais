import { Search, type LucideIcon } from 'lucide-react';
import { useId } from 'react';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

type FilterOption = { value: string; label: string; icon?: LucideIcon };

type FilterBarProps = {
    label: string;
    options: FilterOption[];
    /** Selected option values. Empty means "all". */
    value: string[];
    onChange: (value: string[]) => void;
    search?: {
        value: string;
        onChange: (value: string) => void;
        placeholder: string;
    };
};

/**
 * Filters drive Inertia query params on real pages (router.get with
 * preserveState); the component itself only holds the controls.
 */
export function FilterBar({
    label,
    options,
    value,
    onChange,
    search,
}: FilterBarProps) {
    const searchId = useId();

    return (
        <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <ToggleGroup
                type="multiple"
                variant="outline"
                value={value}
                onValueChange={onChange}
                aria-label={label}
                className="flex-wrap justify-start gap-1.5"
            >
                {options.map(
                    ({
                        value: optionValue,
                        label: optionLabel,
                        icon: Icon,
                    }) => (
                        <ToggleGroupItem
                            key={optionValue}
                            value={optionValue}
                            className="rounded-md! border-l! bg-card px-2.5 text-xs data-[state=on]:bg-accent data-[state=on]:font-semibold data-[state=on]:text-accent-foreground"
                        >
                            {Icon && <Icon className="size-3.5" aria-hidden />}
                            {optionLabel}
                        </ToggleGroupItem>
                    ),
                )}
            </ToggleGroup>
            {search && (
                <div className="relative md:w-64">
                    <label htmlFor={searchId} className="sr-only">
                        {search.placeholder}
                    </label>
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden
                    />
                    <Input
                        id={searchId}
                        type="search"
                        value={search.value}
                        placeholder={search.placeholder}
                        onChange={(event) =>
                            search.onChange(event.target.value)
                        }
                        className="bg-card pl-9"
                    />
                </div>
            )}
        </div>
    );
}
