import { Info } from 'lucide-react';

/**
 * Required on every page that renders DemoData (antislop R-38): the
 * numbers come from the example business, not the owner's ledger.
 */
export function DemoDataBadge() {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-md border border-warning/25 bg-warning-soft px-2 py-0.5 text-xs font-semibold text-warning">
            <Info className="size-3.5" aria-hidden />
            Data contoh
            <span className="sr-only">
                : angka di halaman ini berasal dari usaha contoh, bukan catatan
                Anda.
            </span>
        </span>
    );
}
