import {
    CircleCheck,
    CircleX,
    Coins,
    TriangleAlert,
    Warehouse,
} from 'lucide-react';
import { MetricCount, MetricTile } from '@/components/app/metric';
import { Money } from '@/components/app/money';
import { SectionCard } from '@/components/app/section-card';
import { formatNameList } from '@/lib/format';
import type { ItemsIndexProps } from '@/types/domain';

/** The product list's one focus card: what the stock is worth, and whether anything needs attention. */
export function InventorySummary({
    summary,
}: Pick<ItemsIndexProps, 'summary'>) {
    const low = summary.lowStockCount;
    const negative = summary.negativeStockCount;

    return (
        <SectionCard
            title="Persediaan sekarang"
            description="Dinilai dengan biaya rata-rata tiap bahan dan produk."
            icon={Warehouse}
            tone="focus"
        >
            <div className="grid grid-cols-2 gap-3 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)]">
                <MetricTile
                    label="Nilai persediaan"
                    icon={Coins}
                    value={<Money value={summary.inventoryValue} size="xl" />}
                    detail={`${summary.stockedCount} bahan baku dan produk biasa`}
                    className="col-span-2 md:col-span-1"
                />
                <MetricTile
                    label="Stok menipis"
                    icon={low > 0 ? TriangleAlert : CircleCheck}
                    tone={low > 0 ? 'warning' : 'neutral'}
                    value={<MetricCount value={low} unit="item" />}
                    detail={
                        low > 0
                            ? formatNameList(summary.lowStockNames, low)
                            : 'Semua di atas stok minimum.'
                    }
                />
                <MetricTile
                    label="Stok minus"
                    icon={negative > 0 ? CircleX : CircleCheck}
                    tone={negative > 0 ? 'danger' : 'neutral'}
                    value={<MetricCount value={negative} unit="item" />}
                    detail={
                        negative > 0
                            ? `${formatNameList(summary.negativeStockNames, negative)}. Catat belanja atau koreksi stok.`
                            : 'Tidak ada stok minus.'
                    }
                />
            </div>
        </SectionCard>
    );
}
