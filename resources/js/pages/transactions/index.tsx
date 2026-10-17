import { Head, Link } from '@inertiajs/react';
import {
    ArrowDownLeft,
    ArrowUpRight,
    NotebookText,
    Plus,
    Scale,
    SearchX,
} from 'lucide-react';
import { methodLabel, transactionTypes } from '@/components/app/badges';
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
import { TransactionTable } from '@/components/app/transaction-table';
import { Button } from '@/components/ui/button';
import { useTableQuery } from '@/hooks/use-table-query';
import { create, index } from '@/routes/transactions';
import type {
    PaymentMethod,
    TransactionSource,
    TransactionsIndexProps,
    TransactionType,
} from '@/types/domain';

const PERIODS = [
    { key: 'semua', label: 'Semua' },
    { key: 'hari_ini', label: 'Hari ini' },
    { key: 'minggu_ini', label: 'Minggu ini' },
    { key: 'bulan_ini', label: 'Bulan ini' },
] as const;

type PeriodChoice = (typeof PERIODS)[number]['key'];

const typeOptions = (Object.keys(transactionTypes) as TransactionType[]).map(
    (type) => ({
        value: type,
        label: transactionTypes[type].label,
        icon: transactionTypes[type].icon,
    }),
);

// A purchase reads "Belum dibayar" and a sale "Belum lunas"; the filter uses the neutral word.
const methodOptions = (
    ['tunai', 'non_tunai', 'kredit', 'pribadi'] as PaymentMethod[]
).map((method) => ({
    value: method,
    label: method === 'kredit' ? 'Belum lunas' : methodLabel('biaya', method),
}));

const sourceOptions: Array<{ value: TransactionSource; label: string }> = [
    { value: 'web', label: 'Web' },
    { value: 'wa', label: 'WhatsApp' },
    { value: 'toko', label: 'Halaman toko' },
];

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
                        icon={NotebookText}
                        title="Belum ada transaksi"
                        description="Mulai dari penjualan hari ini. Stok dan laba langsung dihitung."
                        action={
                            <Button asChild>
                                <Link
                                    href={create({
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
                                        periods={[...PERIODS]}
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
