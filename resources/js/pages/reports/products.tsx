import { Head, Link } from '@inertiajs/react';
import { Package, PackageCheck } from 'lucide-react';
import { useState } from 'react';
import { MarginLabel } from '@/components/app/badges';
import { ChoiceGroup } from '@/components/app/choice-group';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { BreakdownBars } from '@/features/reports/breakdown-bars';
import { PrintHeader } from '@/features/reports/print-header';
import { ReportLead } from '@/features/reports/report-lead';
import { ReportToolbar } from '@/features/reports/report-toolbar';
import { formatDate, formatQty, formatRupiah } from '@/lib/format';
import { edit } from '@/routes/items';
import reports from '@/routes/reports';
import { create as createTransaction } from '@/routes/transactions';
import type { ProductsReportProps } from '@/types/domain';

type Order = 'profit' | 'sales' | 'qty';

const ORDER_LABELS: Record<Order, string> = {
    profit: 'Paling untung',
    sales: 'Penjualan terbesar',
    qty: 'Paling laku',
};

/** Produk terlaris dan margin: what sells, what earns, and what sits still. */
export default function Products(props: ProductsReportProps) {
    const { products, slow, period, business, story } = props;
    const [order, setOrder] = useState<Order>('profit');
    const scope = period.key ? period.label.toLowerCase() : period.label;
    const sorted = [...products].sort((a, b) => b[order] - a[order]);
    const frozen = slow.reduce((sum, item) => sum + item.value, 0);
    const [mostProfit] = [...products].sort((a, b) => b.profit - a.profit);
    const thinnest = products
        .filter((product) => product.margin !== null)
        .reduce<(typeof products)[number] | null>(
            (lowest, product) =>
                lowest === null || (product.margin ?? 0) < (lowest.margin ?? 0)
                    ? product
                    : lowest,
            null,
        );

    return (
        <>
            <Head title="Produk terlaris" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PrintHeader
                    business={business.name}
                    title="Produk terlaris dan margin"
                    scope={period.label}
                />
                <PageHeader
                    className="print:hidden"
                    title="Produk terlaris dan margin"
                    description={`Produk yang terjual ${scope}, dan yang stoknya diam.`}
                    actions={<ReportToolbar frame={props} />}
                />

                <ReportLead story={story} />

                <div className="grid grid-cols-1 gap-4 md:gap-6 lg:grid-cols-12">
                    <SectionCard
                        title="Produk mana yang paling menguntungkan?"
                        className="lg:col-span-8"
                    >
                        {sorted.length === 0 ? (
                            <EmptyState
                                icon={Package}
                                title={`Belum ada penjualan ${scope}`}
                                description="Laporan ini terisi sendiri dari catatan penjualan."
                                action={
                                    <Button asChild>
                                        <Link
                                            href={createTransaction({
                                                query: { jenis: 'penjualan' },
                                            })}
                                        >
                                            Catat penjualan
                                        </Link>
                                    </Button>
                                }
                            />
                        ) : (
                            <>
                                <p className="mb-3 text-sm">
                                    {mostProfit.name}, dengan laba{' '}
                                    <span className="whitespace-nowrap">
                                        <Money
                                            value={mostProfit.profit}
                                            size="sm"
                                            className="font-semibold"
                                        />
                                        .
                                    </span>
                                </p>
                                <div className="mb-4 print:hidden">
                                    <ChoiceGroup
                                        label="Urutkan"
                                        value={order}
                                        options={(
                                            Object.keys(ORDER_LABELS) as Order[]
                                        ).map((value) => ({
                                            value,
                                            label: ORDER_LABELS[value],
                                        }))}
                                        onChange={setOrder}
                                        wrap
                                    />
                                </div>
                                <BreakdownBars
                                    label={ORDER_LABELS[order]}
                                    rows={sorted.map((product) => ({
                                        label: product.name,
                                        amount:
                                            order === 'qty'
                                                ? product.profit
                                                : product[order],
                                        href: edit.url(product.id),
                                        hint: `${formatQty(product.qty, product.unit)} · penjualan ${formatRupiah(product.sales)} · laba ${formatRupiah(product.profit)}`,
                                        note: product.margin !== null && (
                                            <MarginLabel
                                                percent={product.margin}
                                            />
                                        ),
                                    }))}
                                />
                            </>
                        )}
                    </SectionCard>

                    <div className="flex min-w-0 flex-col gap-4 md:gap-6 lg:col-span-4">
                        {thinnest && (
                            <SectionCard title="Margin mana yang paling tipis?">
                                <p className="text-sm">
                                    <Link
                                        href={edit(thinnest.id)}
                                        className="font-medium underline underline-offset-4"
                                    >
                                        {thinnest.name}
                                    </Link>{' '}
                                    menyisakan paling sedikit dari tiap
                                    penjualan.
                                </p>
                                <div className="mt-2 flex items-center justify-between gap-3">
                                    <span className="text-sm text-muted-foreground">
                                        Margin
                                    </span>
                                    <MarginLabel
                                        percent={thinnest.margin ?? 0}
                                    />
                                </div>
                            </SectionCard>
                        )}
                        <SectionCard
                            title="Stok mana yang diam?"
                            description="Masih ada stoknya, tidak terjual 30 hari terakhir."
                            className="flex-1"
                        >
                            {slow.length === 0 ? (
                                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <PackageCheck
                                        className="size-4"
                                        aria-hidden
                                    />
                                    Tidak ada. Semua stok masih bergerak.
                                </p>
                            ) : (
                                <>
                                    <p className="mb-2 text-sm">
                                        Uang{' '}
                                        <Money
                                            value={frozen}
                                            size="sm"
                                            className="font-semibold"
                                        />{' '}
                                        tertahan di stok ini.
                                    </p>
                                    <ul className="flex flex-col divide-y">
                                        {slow.map((item) => (
                                            <li
                                                key={item.id}
                                                className="flex items-start justify-between gap-3 py-2.5 text-sm last:pb-0"
                                            >
                                                <div className="min-w-0">
                                                    <p className="font-medium">
                                                        {item.name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {formatQty(
                                                            item.stock,
                                                            item.unit,
                                                        )}{' '}
                                                        ·{' '}
                                                        {item.lastSoldAt
                                                            ? `terakhir ${formatDate(item.lastSoldAt)}`
                                                            : 'belum pernah terjual'}
                                                    </p>
                                                </div>
                                                <Money
                                                    value={item.value}
                                                    size="sm"
                                                />
                                            </li>
                                        ))}
                                    </ul>
                                </>
                            )}
                        </SectionCard>
                    </div>
                </div>
            </div>
        </>
    );
}

Products.layout = {
    breadcrumbs: [
        { title: 'Laporan', href: reports.index() },
        { title: 'Produk terlaris', href: reports.products() },
    ],
};
