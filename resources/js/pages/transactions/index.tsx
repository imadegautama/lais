import { Head, Link } from '@inertiajs/react';
import {
    ArrowDownLeft,
    ArrowUpRight,
    NotebookText,
    Plus,
    Scale,
    SearchX,
} from 'lucide-react';
import { transactionTypes } from '@/components/app/badges';
import {
    ChipFilter,
    FilterBar,
    SearchFilter,
} from '@/components/app/filter-bar';
import { MetricTile } from '@/components/app/metric';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { PeriodPicker } from '@/components/app/period-picker';
import { QuickRecordSheet } from '@/components/app/quick-record-sheet';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { TransactionTable } from '@/features/transactions/transaction-table';
import { useTableQuery } from '@/hooks/use-table-query';
import { create, index } from '@/routes/transactions';
import type { TransactionsIndexProps } from '@/types/domain';
import {
    paymentMethodLabels,
    Period,
    periodLabels,
    TransactionSource,
    transactionSourceLabels,
    TransactionType,
} from '@/types/enums';

type PeriodChoice = Period | 'semua';

const periods: Array<{ key: PeriodChoice; label: string }> = [
    { key: 'semua', label: 'Semua' },
    ...Object.values(Period).map((key) => ({ key, label: periodLabels[key] })),
];

const typeOptions = (Object.keys(transactionTypes) as TransactionType[]).map(
    (type) => ({
        value: type,
        label: transactionTypes[type].label,
        icon: transactionTypes[type].icon,
    }),
);

// The filter uses the neutral words ("Belum lunas" for credit either way).
const methodOptions = Object.entries(paymentMethodLabels).map(
    ([value, label]) => ({ value, label }),
);

// shortcut: receipt photos (ocr) are not recorded yet, so they get no chip; add it with that feature.
const sourceOptions = Object.values(TransactionSource)
    .filter((source) => source !== TransactionSource.Ocr)
    .map((value) => ({ value, label: transactionSourceLabels[value] }));

const otherTypes = Object.values(TransactionType).filter(
    (type) => type !== TransactionType.Penjualan,
);

export default function TransactionsIndex({
    transactions,
    hasTransactions,
}: TransactionsIndexProps) {
    const table = useTableQuery(transactions, 'transactions');
    const { summary } = transactions;
    const period = ((table.query.periode as string[] | undefined)?.[0] ??
        'semua') as PeriodChoice;
    const net = summary.cashIn - summary.cashOut;

    return (
        <>
            <Head title="Transaksi" />
            <div className="flex flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PageHeader
                    title="Transaksi"
                    description="Semua penjualan, belanja, biaya, dan pergerakan modal."
                    actions={
                        hasTransactions && (
                            <QuickRecordSheet
                                trigger={
                                    <Button>
                                        <Plus aria-hidden />
                                        Catat transaksi
                                    </Button>
                                }
                            />
                        )
                    }
                />

                {!hasTransactions ? (
                    <EmptyState
                        variant="page"
                        icon={NotebookText}
                        title="Belum ada transaksi"
                        description="Mulai dari penjualan hari ini. Stok, kas, dan laba langsung dihitung saat disimpan."
                        action={
                            <div className="flex flex-col gap-4">
                                <Button asChild className="self-start">
                                    <Link
                                        href={create({
                                            query: {
                                                jenis: TransactionType.Penjualan,
                                            },
                                        })}
                                    >
                                        Catat penjualan
                                    </Link>
                                </Button>
                                <div className="flex flex-col gap-2">
                                    <p className="text-sm text-muted-foreground">
                                        Atau catat yang lain:
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        {otherTypes.map((type) => {
                                            const { label, icon: Icon } =
                                                transactionTypes[type];

                                            return (
                                                <Button
                                                    key={type}
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={create({
                                                            query: {
                                                                jenis: type,
                                                            },
                                                        })}
                                                    >
                                                        <Icon aria-hidden />
                                                        {label}
                                                    </Link>
                                                </Button>
                                            );
                                        })}
                                    </div>
                                </div>
                            </div>
                        }
                    />
                ) : (
                    <>
                        <SectionCard
                            title="Uang usaha"
                            description={`${summary.count} transaksi sesuai saringan. Belum lunas dan uang pribadi tidak dihitung.`}
                            icon={Scale}
                            tone="focus"
                            busy={table.isLoading}
                        >
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <MetricTile
                                    label="Uang masuk"
                                    icon={ArrowDownLeft}
                                    value={
                                        <Money
                                            value={summary.cashIn}
                                            size="xl"
                                        />
                                    }
                                    detail="Tunai dan non-tunai"
                                />
                                <MetricTile
                                    label="Uang keluar"
                                    icon={ArrowUpRight}
                                    value={
                                        <Money
                                            value={summary.cashOut}
                                            size="xl"
                                        />
                                    }
                                    detail="Termasuk ambil pribadi"
                                />
                                <MetricTile
                                    label="Selisih"
                                    icon={Scale}
                                    value={<Money value={net} size="xl" />}
                                    detail="Masuk dikurangi keluar."
                                />
                            </div>
                        </SectionCard>

                        <SectionCard
                            title="Daftar transaksi"
                            description="Terbaru di atas. Klik keterangan untuk melihat rinciannya."
                        >
                            <div className="flex flex-col gap-4">
                                <div className="flex flex-col gap-2">
                                    <PeriodPicker
                                        fill
                                        periods={periods}
                                        value={period}
                                        onChange={(next) =>
                                            table.set({
                                                periode:
                                                    next === 'semua'
                                                        ? []
                                                        : [next],
                                            })
                                        }
                                    />
                                    <FilterBar>
                                        <ChipFilter
                                            label="Saring jenis"
                                            options={typeOptions}
                                            {...table.filter('jenis')}
                                        />
                                        <SearchFilter
                                            placeholder="Cari keterangan atau nama"
                                            {...table.search}
                                        />
                                    </FilterBar>
                                    <div className="flex flex-wrap gap-x-4 gap-y-2">
                                        <ChipFilter
                                            label="Saring cara bayar"
                                            options={methodOptions}
                                            {...table.filter('metode')}
                                        />
                                        <ChipFilter
                                            label="Saring sumber"
                                            options={sourceOptions}
                                            {...table.filter('sumber')}
                                        />
                                    </div>
                                </div>
                                <TransactionTable
                                    rows={transactions.data}
                                    server={table}
                                    linked
                                    empty={
                                        <EmptyState
                                            icon={SearchX}
                                            title="Tidak ada yang cocok"
                                            description="Coba periode lain, kata lain, atau hapus saringan."
                                            action={
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={table.reset}
                                                >
                                                    Hapus saringan
                                                </Button>
                                            }
                                        />
                                    }
                                />
                            </div>
                        </SectionCard>
                    </>
                )}
            </div>
        </>
    );
}

TransactionsIndex.layout = {
    breadcrumbs: [{ title: 'Transaksi', href: index() }],
};
