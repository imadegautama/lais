import { MarginLabel, THIN_MARGIN_PERCENT } from '@/components/app/badges';
import { MetricInline } from '@/components/app/metric';
import { Money } from '@/components/app/money';
import { SectionCard } from '@/components/app/section-card';
import { ItemKind } from '@/types/enums';
import { margin } from './cost';

function Missing({ children }: { children: string }) {
    return <span className="text-sm text-muted-foreground">{children}</span>;
}

/** Cost, price, and margin per sale, next to the form, as they are typed. */
export function CostPanel({
    kind,
    unitCost,
    price,
    unit,
}: {
    kind: ItemKind;
    unitCost: number | null;
    price: number | null;
    unit: string;
}) {
    const result = margin(price, unitCost);
    const percent = result?.percent ?? null;
    const recipe = kind === ItemKind.Komposisi;

    return (
        <SectionCard
            title="Untung per jual"
            tone="focus"
            description={
                recipe
                    ? `Dihitung langsung dari resep, per ${unit || 'porsi'}.`
                    : `Dari harga beli per ${unit || 'satuan'}.`
            }
            className="lg:sticky lg:top-18"
        >
            <div className="flex flex-col divide-y" aria-live="polite">
                <MetricInline
                    label={recipe ? 'HPP' : 'Biaya per unit'}
                    value={
                        unitCost === null ? (
                            <Missing>Belum diisi</Missing>
                        ) : (
                            <Money value={unitCost} />
                        )
                    }
                />
                <MetricInline
                    label="Harga jual"
                    value={
                        price === null ? (
                            <Missing>Belum diisi</Missing>
                        ) : (
                            <Money value={price} />
                        )
                    }
                />
                <MetricInline
                    label="Margin"
                    value={
                        result === null ? (
                            <Missing>Isi harga dan biaya</Missing>
                        ) : (
                            <Money value={result.rupiah} size="lg" />
                        )
                    }
                    detail={
                        percent !== null && <MarginLabel percent={percent} />
                    }
                />
            </div>
            {percent !== null && percent < THIN_MARGIN_PERCENT && (
                <p
                    role="status"
                    className="mt-3 rounded-lg bg-destructive-soft px-3 py-2 text-sm text-foreground"
                >
                    Margin di bawah {THIN_MARGIN_PERCENT}%. Naikkan harga jual
                    atau cek lagi takaran resepnya.
                </p>
            )}
        </SectionCard>
    );
}
