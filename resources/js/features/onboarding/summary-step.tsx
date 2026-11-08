import { Award, CircleCheck, Trophy } from 'lucide-react';
import { LabelBadge } from '@/components/app/badges';
import { MetricInline } from '@/components/app/metric';
import { Money } from '@/components/app/money';
import { formatRupiah } from '@/lib/format';
import type { openingBalance } from './onboarding';

/**
 * The last step: the badges earned on the way, then the first balance sheet,
 * balanced because modal awal is the difference.
 */
export function SummaryStep({
    books,
    badges,
}: {
    books: ReturnType<typeof openingBalance>;
    badges: string[];
}) {
    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-col items-center gap-3 rounded-xl border bg-card p-5 text-center">
                <span className="flex size-14 items-center justify-center rounded-full bg-brand-soft text-brand-strong motion-safe:animate-in motion-safe:duration-500 motion-safe:zoom-in-50">
                    <Trophy className="size-7" aria-hidden />
                </span>
                <div>
                    <p className="text-lg font-semibold">Semua langkah beres</p>
                    <p className="text-sm text-muted-foreground">
                        Kamu mengumpulkan {badges.length} lencana. Periksa
                        neraca awal di bawah, lalu simpan.
                    </p>
                </div>
                <ul
                    aria-label="Lencana yang terkumpul"
                    className="flex flex-wrap justify-center gap-2"
                >
                    {badges.map((badge, index) => (
                        <li
                            key={badge}
                            // One after another, once; a reduced-motion screen shows them all at once.
                            style={{ animationDelay: `${index * 80}ms` }}
                            className="motion-safe:animate-in motion-safe:duration-300 motion-safe:zoom-in-90 motion-safe:[animation-fill-mode:backwards] motion-safe:fade-in"
                        >
                            <LabelBadge
                                icon={Award}
                                label={badge}
                                tone="success"
                            />
                        </li>
                    ))}
                </ul>
            </div>
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
