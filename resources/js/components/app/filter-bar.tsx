import { Search, type LucideIcon } from 'lucide-react';
import { useId, type ReactNode } from 'react';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

/**
 * Layout for the filters above a table. Every filter inside is a plain
 * { value, onChange } control, so a new kind of filter is just a component:
 * <FilterBar><ChipFilter {...table.filter('jenis')} /><SearchFilter {...table.search} /></FilterBar>
 */
export function FilterBar({ children }: { children: ReactNode }) {
    return (
        <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            {children}
        </div>
    );
}

type ChipOption = { value: string; label: string; icon?: LucideIcon };

type ChipFilterProps = {
    label: string;
    options: ChipOption[];
    /** Selected option values. Empty means "all". */
    value: string[];
    onChange: (value: string[]) => void;
};

/** Multi-select chips, e.g. item kinds or transaction types. */
export function ChipFilter({
    label,
    options,
    value,
    onChange,
}: ChipFilterProps) {
    return (
        <ToggleGroup
            type="multiple"
            variant="outline"
            value={value}
            onValueChange={onChange}
            aria-label={label}
            className="flex-wrap justify-start gap-1.5"
        >
            {options.map(
                ({ value: optionValue, label: optionLabel, icon: Icon }) => (
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
    );
}

type SearchFilterProps = {
    /** Also the accessible label, so name what can be searched. */
    placeholder: string;
    value: string;
    onChange: (value: string) => void;
};

export function SearchFilter({
    placeholder,
    value,
    onChange,
}: SearchFilterProps) {
    const id = useId();

    return (
        <div className="relative md:w-64">
            <label htmlFor={id} className="sr-only">
                {placeholder}
            </label>
            <Search
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden
            />
            <Input
                id={id}
                type="search"
                value={value}
                placeholder={placeholder}
                onChange={(event) => onChange(event.target.value)}
                className="bg-card pl-9"
            />
        </div>
    );
}
