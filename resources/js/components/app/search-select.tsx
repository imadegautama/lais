import { Check, ChevronsUpDown, Plus } from 'lucide-react';
import { useState } from 'react';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type SearchSelectOption = {
    value: string;
    label: string;
    /** Shown on the right in a quieter tone, e.g. "Rp14.000/kg". */
    description?: string;
    /** Options with the same group are listed under one heading. */
    group?: string;
    disabled?: boolean;
    /** Extra words the search should match, such as aliases. */
    keywords?: string[];
};

type SearchSelectProps = {
    /** Pair with <Label htmlFor> so the field has a visible name. */
    id?: string;
    value: string;
    onChange: (value: string) => void;
    options: SearchSelectOption[];
    placeholder: string;
    searchPlaceholder: string;
    emptyText: string;
    invalid?: boolean;
    /** Offers the typed text as a value of its own, e.g. (q) => `Pakai satuan "${q}"`. */
    creatable?: (query: string) => string;
    className?: string;
};

/**
 * A field that opens a searchable list. Use it instead of <Select> once a
 * list can grow past a handful of options (DESIGN.md §6).
 */
export function SearchSelect({
    id,
    value,
    onChange,
    options,
    placeholder,
    searchPlaceholder,
    emptyText,
    invalid,
    creatable,
    className,
}: SearchSelectProps) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const selected = options.find((option) => option.value === value);
    const typed = query.trim();
    const canCreate =
        creatable !== undefined &&
        typed !== '' &&
        !options.some(
            (option) => option.label.toLowerCase() === typed.toLowerCase(),
        );
    const groups = [...new Set(options.map((option) => option.group ?? ''))];

    const toggle = (next: boolean) => {
        setOpen(next);

        if (!next) {
            setQuery('');
        }
    };

    const choose = (next: string) => {
        onChange(next);
        toggle(false);
    };

    return (
        <Popover open={open} onOpenChange={toggle}>
            <PopoverTrigger asChild>
                <button
                    id={id}
                    type="button"
                    role="combobox"
                    aria-expanded={open}
                    aria-invalid={invalid || undefined}
                    className={cn(
                        'flex h-9 w-full min-w-0 items-center justify-between gap-2 rounded-md border border-input bg-card px-3 text-left text-base shadow-field outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring aria-invalid:border-destructive md:text-sm pointer-coarse:h-11',
                        className,
                    )}
                >
                    <span
                        className={cn(
                            'truncate',
                            !selected &&
                                value === '' &&
                                'text-muted-foreground',
                        )}
                    >
                        {selected?.label ?? (value || placeholder)}
                    </span>
                    <ChevronsUpDown
                        className="size-4 shrink-0 text-muted-foreground"
                        aria-hidden
                    />
                </button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="w-(--radix-popover-trigger-width) min-w-60 p-0"
            >
                <Command>
                    <CommandInput
                        placeholder={searchPlaceholder}
                        value={query}
                        onValueChange={setQuery}
                        className="text-base md:text-sm"
                    />
                    <CommandList className="max-h-72">
                        <CommandEmpty>{emptyText}</CommandEmpty>
                        {groups.map((group) => (
                            <CommandGroup
                                key={group}
                                heading={group === '' ? undefined : group}
                            >
                                {options
                                    .filter(
                                        (option) =>
                                            (option.group ?? '') === group,
                                    )
                                    .map((option) => (
                                        <CommandItem
                                            key={option.value}
                                            value={option.label}
                                            keywords={option.keywords}
                                            disabled={option.disabled}
                                            onSelect={() =>
                                                choose(option.value)
                                            }
                                            className="min-h-10 pointer-coarse:min-h-11"
                                        >
                                            <Check
                                                className={cn(
                                                    'text-brand-strong',
                                                    option.value !== value &&
                                                        'invisible',
                                                )}
                                                aria-hidden
                                            />
                                            <span className="min-w-0 flex-1 truncate">
                                                {option.label}
                                            </span>
                                            {option.description && (
                                                <span className="num shrink-0 text-xs text-muted-foreground">
                                                    {option.description}
                                                </span>
                                            )}
                                        </CommandItem>
                                    ))}
                            </CommandGroup>
                        ))}
                        {canCreate && (
                            <CommandGroup forceMount>
                                <CommandItem
                                    forceMount
                                    value={`buat:${typed}`}
                                    onSelect={() => choose(typed)}
                                    className="min-h-10 font-medium pointer-coarse:min-h-11"
                                >
                                    <Plus
                                        className="text-brand-strong"
                                        aria-hidden
                                    />
                                    {creatable(typed)}
                                </CommandItem>
                            </CommandGroup>
                        )}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
