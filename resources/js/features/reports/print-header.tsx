import { formatDate } from '@/lib/format';

/** On paper only: whose report, which one, for when, and when it was printed (PRD M6). */
export function PrintHeader({
    business,
    title,
    scope,
}: {
    business: string;
    title: string;
    /** "Bulan ini" or "per 31 Okt 2026". */
    scope: string;
}) {
    return (
        <header className="mb-4 hidden border-b pb-3 print:block">
            <p className="text-sm">{business}</p>
            <h1 className="text-xl font-bold">
                {title} · {scope}
            </h1>
            <p className="text-xs text-muted-foreground">
                Dicetak {formatDate(new Date(), 'datetime')}
            </p>
        </header>
    );
}
