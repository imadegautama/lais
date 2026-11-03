import { Info } from 'lucide-react';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { terms, type Term } from '@/features/reports/vocabulary';

/** ⓘ beside a term: opens on tap, click, or keyboard, never on hover alone. */
export function TermHint({ term, label }: { term: Term; label: string }) {
    // "HPP" stays an acronym; "Laba kotor" reads as "laba kotor".
    const spoken = label === label.toUpperCase() ? label : label.toLowerCase();

    return (
        <Popover>
            <PopoverTrigger
                className="inline-flex size-6 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none print:hidden pointer-coarse:size-11"
                aria-label={`Apa itu ${spoken}?`}
            >
                <Info className="size-4" aria-hidden />
            </PopoverTrigger>
            <PopoverContent className="w-64 text-sm print:hidden" side="top">
                {terms[term]}
            </PopoverContent>
        </Popover>
    );
}
