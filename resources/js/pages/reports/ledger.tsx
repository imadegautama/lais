import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, NotebookText } from 'lucide-react';
import { useState } from 'react';
import {
    AppDataTable,
    type DataTableColumn,
} from '@/components/app/app-data-table';
import { SearchFilter } from '@/components/app/filter-bar';
import { MetricInline } from '@/components/app/metric';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { PrintHeader } from '@/features/reports/print-header';
import { ReportToolbar } from '@/features/reports/report-toolbar';
import { TermHint } from '@/components/app/term-hint';
import { BalanceArea } from '@/features/reports/trend-chart';
import { usePrinting } from '@/features/reports/use-printing';
import { formatDate } from '@/lib/format';
import reports from '@/routes/reports';
import { create as createTransaction } from '@/routes/transactions';
import type { LedgerProps } from '@/types/domain';

type Line = LedgerProps['ledger']['lines'][number] & { order: number };

const columns: Array<DataTableColumn<Line>> = [
    {
        id: 'tanggal',
        // Lines arrive oldest first, so the position also settles ties within a minute.
        accessorKey: 'order',
        header: 'Tanggal',
        sortFn: 'basic',
        cell: ({ row }) => (
            <span className="num whitespace-nowrap">
                {formatDate(row.original.occurredAt)}
                <span className="block text-xs text-muted-foreground">
                    {formatDate(row.original.occurredAt, 'time')}
                </span>
            </span>
        ),
    },
    {
        id: 'keterangan',
        header: 'Keterangan',
        enableSorting: false,
        cell: ({ row }) => (
            <span className="font-medium whitespace-normal">
                {row.original.description}
            </span>
        ),
    },
    {
        id: 'debit',
        header: () => (
            <span className="inline-flex items-center gap-1">
                Debit <TermHint term="debit" label="Debit" />
            </span>
        ),
        enableSorting: false,
        cell: ({ row }) =>
            row.original.debit > 0 && (
                <Money value={row.original.debit} size="sm" />
            ),
    },
    {
        id: 'kredit',
        header: () => (
            <span className="inline-flex items-center gap-1">
                Kredit <TermHint term="credit" label="Kredit" />
            </span>
        ),
        enableSorting: false,
        cell: ({ row }) =>
            row.original.credit > 0 && (
                <Money value={row.original.credit} size="sm" />
            ),
    },
    {
        id: 'saldo',
        header: 'Saldo',
        enableSorting: false,
        cell: ({ row }) => <Money value={row.original.balance} size="sm" />,
    },
];

function LineItem({ line }: { line: Line }) {
    return (
        <div className="flex items-start justify-between gap-3 text-sm">
            <div className="min-w-0">
                <p className="font-medium">{line.description}</p>
                <p className="text-xs text-muted-foreground">
                    {formatDate(line.occurredAt, 'time')} ·{' '}
                    {line.debit > 0 ? 'Debit' : 'Kredit'}
                </p>
            </div>
            <div className="flex shrink-0 flex-col items-end gap-0.5">
                <Money
                    value={line.debit > 0 ? line.debit : line.credit}
                    size="sm"
                />
                <span className="text-xs text-muted-foreground">
                    Saldo{' '}
                    <Money
                        value={line.balance}
                        size="sm"
                        className="font-normal text-muted-foreground"
                    />
                </span>
            </div>
        </div>
    );
}

/** Buku besar: what one account held, every line of the period, and where it ended. */
export default function Ledger(props: LedgerProps) {
    const { account, ledger, period, business, query } = props;
    const printing = usePrinting();
    const [search, setSearch] = useState('');
    const title = `Buku besar ${account.code} ${account.name}`;
    const scope = period.key ? period.label.toLowerCase() : period.label;
    const wanted = search.trim().toLowerCase();
    const lines = ledger.lines
        .map((line, order) => ({ ...line, order }))
        .filter(
            (line) =>
                wanted === '' ||
                line.description.toLowerCase().includes(wanted),
        );
    const balances = [
        ...new Map(
            ledger.lines.map((line) => [
                line.occurredAt.slice(0, 10),
                line.balance,
            ]),
        ),
    ].map(([date, balance]) => ({ date, balance }));
    const debit = ledger.lines.reduce((sum, line) => sum + line.debit, 0);
    const credit = ledger.lines.reduce((sum, line) => sum + line.credit, 0);

    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PrintHeader
                    business={business.name}
                    title={title}
                    scope={period.label}
                />
                <Button
                    variant="ghost"
                    className="-ml-3 w-fit print:hidden"
                    asChild
                >
                    <Link href={reports.chart({ query })}>
                        <ArrowLeft aria-hidden />
                        Daftar akun
                    </Link>
                </Button>
                <PageHeader
                    className="print:hidden"
                    title={title}
                    description={`Semua mutasi akun ini ${scope}, dengan saldo berjalan.`}
                    actions={<ReportToolbar frame={props} />}
                />

                <div className="grid grid-cols-1 items-start gap-4 md:gap-6 xl:grid-cols-12">
                    <SectionCard title="Mutasi" className="xl:col-span-8">
                        {ledger.lines.length > 0 && (
                            <div className="mb-3 print:hidden">
                                <SearchFilter
                                    placeholder="Cari mutasi"
                                    value={search}
                                    onChange={setSearch}
                                />
                            </div>
                        )}
                        <div className="flex items-baseline justify-between gap-3 border-b pb-2 text-sm">
                            <span className="text-muted-foreground">
                                Saldo awal
                            </span>
                            <Money value={ledger.opening} />
                        </div>

                        <AppDataTable
                            key={printing ? 'kertas' : 'layar'}
                            caption={`Mutasi ${account.name}`}
                            columns={columns}
                            data={lines}
                            getRowId={(line) => String(line.order)}
                            initialSorting={[{ id: 'tanggal', desc: true }]}
                            pageSize={printing ? undefined : 20}
                            layout={{
                                tanggal: { className: 'w-28' },
                                debit: { align: 'right', className: 'w-32' },
                                kredit: { align: 'right', className: 'w-32' },
                                saldo: { align: 'right', className: 'w-32' },
                            }}
                            renderMobileItem={(line) => (
                                <LineItem line={line} />
                            )}
                            mobileGroupBy={(line) =>
                                formatDate(line.occurredAt, 'long')
                            }
                            empty={
                                ledger.lines.length === 0 ? (
                                    <EmptyState
                                        icon={NotebookText}
                                        title={`Tidak ada mutasi ${scope}`}
                                        description="Buku besar terisi sendiri dari transaksi yang memakai akun ini."
                                        action={
                                            <Button asChild>
                                                <Link
                                                    href={createTransaction()}
                                                >
                                                    Catat transaksi
                                                </Link>
                                            </Button>
                                        }
                                    />
                                ) : (
                                    <EmptyState
                                        icon={NotebookText}
                                        title="Tidak ada mutasi yang cocok"
                                        description="Coba kata lain."
                                        action={
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => setSearch('')}
                                            >
                                                Tampilkan semua mutasi
                                            </Button>
                                        }
                                    />
                                )
                            }
                        />

                        <div className="mt-3 flex items-baseline justify-between gap-3 border-t-2 border-foreground/80 pt-3 font-semibold">
                            <span>Saldo akhir</span>
                            <Money value={ledger.closing} size="lg" />
                        </div>
                    </SectionCard>

                    <div className="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2 md:gap-6 xl:sticky xl:top-20 xl:col-span-4 xl:flex xl:flex-col print:hidden">
                        <SectionCard title="Ringkasan">
                            <div className="flex flex-col divide-y">
                                <MetricInline
                                    label={
                                        <span className="inline-flex items-center gap-1">
                                            Total debit
                                            <TermHint
                                                term="debit"
                                                label="Debit"
                                            />
                                        </span>
                                    }
                                    value={<Money value={debit} />}
                                />
                                <MetricInline
                                    label={
                                        <span className="inline-flex items-center gap-1">
                                            Total kredit
                                            <TermHint
                                                term="credit"
                                                label="Kredit"
                                            />
                                        </span>
                                    }
                                    value={<Money value={credit} />}
                                />
                                <MetricInline
                                    label="Jumlah mutasi"
                                    value={
                                        <span className="num font-semibold">
                                            {ledger.lines.length}
                                        </span>
                                    }
                                />
                            </div>
                        </SectionCard>
                        {balances.length > 1 && (
                            <SectionCard title="Bagaimana saldonya bergerak?">
                                <BalanceArea
                                    caption={`Saldo ${account.name} tiap hari`}
                                    data={balances}
                                    opening={ledger.opening}
                                />
                            </SectionCard>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

Ledger.layout = {
    breadcrumbs: [
        { title: 'Laporan', href: reports.index() },
        { title: 'Buku besar', href: reports.chart() },
    ],
};
