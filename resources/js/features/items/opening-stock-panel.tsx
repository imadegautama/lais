import { Money } from '@/components/app/money';
import { SectionCard } from '@/components/app/section-card';
import { openingValue } from './cost';

/** Bahan baku has no price, so the side shows what the opening stock adds to the books. */
export function OpeningStockPanel({
    qty,
    unitCost,
    unit,
}: {
    qty: number;
    unitCost: number | null;
    unit: string;
}) {
    const value = openingValue(qty, unitCost);

    return (
        <SectionCard
            title="Nilai stok awal"
            tone="focus"
            description={`Jumlah × harga beli per ${unit || 'satuan'}.`}
            className="lg:sticky lg:top-18"
        >
            <div aria-live="polite" className="flex flex-col gap-2">
                {value === null ? (
                    <p className="text-sm text-muted-foreground">
                        Belum diisi. Stok bisa dicatat nanti saat belanja.
                    </p>
                ) : (
                    <>
                        <Money value={value} size="xl" />
                        <p className="text-sm text-muted-foreground">
                            Dicatat sebagai modal awal di pembukuan.
                        </p>
                    </>
                )}
            </div>
        </SectionCard>
    );
}
