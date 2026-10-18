import { CircleCheck } from 'lucide-react';
import { LabelBadge } from '@/components/app/badges';
import { MetricInline } from '@/components/app/metric';
import { Money } from '@/components/app/money';
import { formatRupiah } from '@/lib/format';
import type { openingBalance } from './onboarding';

/** Langkah 6: the first balance sheet, balanced because modal awal is the difference. */
export function SummaryStep({
    books,
}: {
    books: ReturnType<typeof openingBalance>;
}) {
    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-col divide-y">
                <MetricInline
                    label="Kas & rekening"
                    value={<Money value={books.cash} />}
                />
                <MetricInline
                    label="Peralatan"
                    value={<Money value={books.equipment} />}
                />
                <MetricInline
                    label="Persediaan"
                    value={<Money value={books.inventory} />}
                    detail="Stok awal × biaya per unit."
                />
                <MetricInline
                    label="Piutang pelanggan"
                    value={<Money value={books.receivables} />}
                />
                <MetricInline
                    label="Total aset"
                    value={<Money value={books.assets} size="lg" />}
                />
                <MetricInline
                    label="Utang ke supplier"
                    value={<Money value={books.debts} />}
                />
                <MetricInline
                    label="Modal awal kamu"
                    value={<Money value={books.capital} size="xl" />}
                    detail="Aset dikurangi utang: bagian usaha yang benar-benar milikmu."
                />
            </div>
            <p className="flex flex-wrap items-center gap-2 text-sm">
                <LabelBadge
                    icon={CircleCheck}
                    label="Seimbang"
                    tone="success"
                />
                <span className="num text-muted-foreground">
                    Aset {formatRupiah(books.assets)} = utang{' '}
                    {formatRupiah(books.debts)} + modal awal{' '}
                    {formatRupiah(books.capital)}
                </span>
            </p>
        </div>
    );
}
