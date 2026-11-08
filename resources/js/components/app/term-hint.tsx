import { Info } from 'lucide-react';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { termDefinitions, type Term } from '@/types/enums';

/**
 * Plain words for accounting terms (PRD: istilah diberi penjelasan), shared by
 * the reports and the setup so every screen explains a term the same way.
 * Written in app/Enums/Term.php, the same words Tanya AI and the bot use.
 */
export const terms = termDefinitions;

export type { Term };

/**
 * ⓘ beside a term: opens on tap, click, or keyboard, never on hover alone.
 * As a chip it says "Apa itu …?" out loud, for screens that teach (the setup).
 */
export function TermHint({
    term,
    label,
    chip = false,
}: {
    term: Term;
    label: string;
    chip?: boolean;
}) {
    // "HPP" stays an acronym; "Laba kotor" reads as "laba kotor".
    const spoken = label === label.toUpperCase() ? label : label.toLowerCase();

    return (
        <Popover>
            <PopoverTrigger
                className={
                    chip
                        ? 'inline-flex min-h-8 items-center gap-1.5 rounded-full border bg-card px-3 text-xs font-medium text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none print:hidden pointer-coarse:min-h-11'
                        : 'inline-flex size-6 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none print:hidden pointer-coarse:size-11'
                }
                aria-label={chip ? undefined : `Apa itu ${spoken}?`}
            >
                <Info className="size-4" aria-hidden />
                {chip && `Apa itu ${spoken}?`}
            </PopoverTrigger>
            <PopoverContent className="w-64 text-sm print:hidden" side="top">
                {terms[term]}
            </PopoverContent>
        </Popover>
    );
}
