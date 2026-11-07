import { Head, Link } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AppDataTable,
    type DataTableColumn,
} from '@/components/app/app-data-table';
import { ChoiceGroup } from '@/components/app/choice-group';
import { SearchFilter } from '@/components/app/filter-bar';
import { MetricInline } from '@/components/app/metric';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { PrintHeader } from '@/features/reports/print-header';
import { ReportLead } from '@/features/reports/report-lead';
import {
    ReportToolbar,
    useReportQuery,
} from '@/features/reports/report-toolbar';
import { BalanceArea } from '@/features/reports/trend-chart';
import { usePrinting } from '@/features/reports/use-printing';
import { formatDate } from '@/lib/format';
import reports from '@/routes/reports';
import { create as createTransaction } from '@/routes/transactions';
import type { CashBookProps } from '@/types/domain';

type Flow = 'semua' | 'masuk' | 'keluar';
type BookLine = CashBookProps['book']['lines'][number] & { order: number };

const columns: Array<DataTableColumn<BookLine>> = [
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
                <span className="block text-xs font-normal text-muted-foreground">
                    {row.original.account}
                </span>
            </span>
        ),
    },
    {
        id: 'masuk',
        accessorKey: 'in',
        header: 'Masuk',
        sortFn: 'basic',
        cell: ({ row }) =>
            row.original.in > 0 && (
                <Money value={row.original.in} direction="in" size="sm" />
            ),
    },
    {
        id: 'keluar',
        accessorKey: 'out',
        header: 'Keluar',
        sortFn: 'basic',
        cell: ({ row }) =>
            row.original.out > 0 && (
                <Money value={row.original.out} direction="out" size="sm" />
            ),
    },
    {
        id: 'saldo',
        header: 'Saldo',
        enableSorting: false,
        cell: ({ row }) => <Money value={row.original.balance} size="sm" />,
    },
];

function LineItem({ line }: { line: BookLine }) {
    return (
        <div className="flex items-start justify-between gap-3 text-sm">
            <div className="min-w-0">
                <p className="font-medium">{line.description}</p>
                <p className="text-xs text-muted-foreground">
                    {formatDate(line.occurredAt, 'time')} · {line.account}
                </p>
            </div>
            <div className="flex shrink-0 flex-col items-end gap-0.5">
                <Money
                    value={line.in > 0 ? line.in : line.out}
                    direction={line.in > 0 ? 'in' : 'out'}
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

/** Buku kas: money in and out, with the running balance. */
export default function CashBook(props: CashBookProps) {
    const { book, moneyAccounts, moneyAccountId, period, business, query } =
        props;
    const report = useReportQuery();
    const printing = usePrinting();
    const [search, setSearch] = useState('');
    const [flow, setFlow] = useState<Flow>('semua');
    const scope = period.key ? period.label.toLowerCase() : period.label;
    const chosen = moneyAccounts.find(
        (account) => account.id === moneyAccountId,
    );
    const rekening: Record<string, string> = moneyAccountId
        ? { rekening: String(moneyAccountId) }
        : {};
    const lines = useMemo(() => {
        const wanted = search.trim().toLowerCase();

        return book.lines
            .map((line, order) => ({ ...line, order }))
            .filter(
                (line) =>
                    (flow === 'semua' ||
                        (flow === 'masuk' ? line.in > 0 : line.out > 0)) &&
                    (wanted === '' ||
                        line.description.toLowerCase().includes(wanted)),
            );
    }, [book.lines, flow, search]);
    // The last balance of each day, oldest first, for the chart.
    const balances = useMemo(
        () =>
            [
                ...new Map(
                    book.lines.map((line) => [
                        line.occurredAt.slice(0, 10),
                        line.balance,
                    ]),
                ),
            ].map(([date, balance]) => ({ date, balance })),
        [book.lines],
    );

    return (
        <>
            <Head title="Buku kas" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PrintHeader
                    business={business.name}
                    title={`Buku kas${chosen ? ` ${chosen.name}` : ''}`}
                    scope={period.label}
                />
                <PageHeader
                    className="print:hidden"
                    title="Buku kas"
                    description={`Uang masuk dan keluar ${scope}, dengan saldo berjalan.`}
                    actions={
                        <ReportToolbar
                            frame={props}
                            extra={rekening}
                            report={report}
                        />
                    }
                />

                <ReportLead story={props.story} />

                <div className="grid grid-cols-1 items-start gap-4 md:gap-6 xl:grid-cols-12">
                    <SectionCard title="Mutasi" className="xl:col-span-8">
                        {book.lines.length > 0 && (
                            <div className="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between print:hidden">
                                <ChoiceGroup
                                    label="Arah uang"
                                    value={flow}
                                    options={[
                                        { value: 'semua', label: 'Semua' },
                                        { value: 'masuk', label: 'Masuk' },
                                        { value: 'keluar', label: 'Keluar' },
                                    ]}
                                    onChange={setFlow}
                                    wrap
                                />
                                <SearchFilter
                                    placeholder="Cari mutasi"
                                    value={search}
                                    onChange={setSearch}
                                />
                            </div>
                        )}

                        <AppDataTable
                            key={printing ? 'kertas' : 'layar'}
                            caption="Mutasi kas"
                            columns={columns}
                            data={lines}
                            getRowId={(line) => String(line.order)}
                            initialSorting={[{ id: 'tanggal', desc: true }]}
                            pageSize={printing ? undefined : 20}
                            layout={{
                                tanggal: { className: 'w-28' },
                                masuk: { align: 'right', className: 'w-28' },
                                keluar: { align: 'right', className: 'w-28' },
                                saldo: { align: 'right', className: 'w-32' },
                            }}
                            renderMobileItem={(line) => (
                                <LineItem line={line} />
                            )}
                            mobileGroupBy={(line) =>
                                formatDate(line.occurredAt, 'long')
                            }
                            empty={
                                book.lines.length === 0 ? (
                                    <EmptyState
                                        icon={BookOpen}
                                        title={`Tidak ada uang masuk atau keluar ${scope}`}
                                        description="Buku kas terisi sendiri dari transaksi yang memakai kas."
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
                                        icon={BookOpen}
                                        title="Tidak ada mutasi yang cocok"
                                        description="Coba kata lain, atau tampilkan semua arah uang."
                                        action={
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => {
                                                    setSearch('');
                                                    setFlow('semua');
                                                }}
                                            >
                                                Tampilkan semua mutasi
                                            </Button>
                                        }
                                    />
                                )
                            }
                        />
                    </SectionCard>

                    <div className="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2 md:gap-6 xl:sticky xl:top-20 xl:col-span-4 xl:flex xl:flex-col print:block">
                        <SectionCard title="Rekening" className="print:hidden">
                            <ChoiceGroup
                                label="Rekening"
                                value={
                                    moneyAccountId === null
                                        ? 'semua'
                                        : String(moneyAccountId)
                                }
                                options={[
                                    { value: 'semua', label: 'Semua' },
                                    ...moneyAccounts.map((account) => ({
                                        value: String(account.id),
                                        label: account.name,
                                    })),
                                ]}
                                onChange={(id) =>
                                    report.visit({
                                        ...query,
                                        ...(id === 'semua'
                                            ? {}
                                            : { rekening: id }),
                                    })
                                }
                                wrap
                            />
                        </SectionCard>

                        <SectionCard title="Ringkasan">
                            <div className="flex flex-col divide-y">
                                <MetricInline
                                    label="Saldo awal"
                                    value={<Money value={book.opening} />}
                                />
                                <MetricInline
                                    label="Total masuk"
                                    value={
                                        <Money
                                            value={book.totalIn}
                                            direction="in"
                                        />
                                    }
                                />
                                <MetricInline
                                    label="Total keluar"
                                    value={
                                        <Money
                                            value={book.totalOut}
                                            direction="out"
                                        />
                                    }
                                />
                                <MetricInline
                                    label="Saldo akhir"
                                    value={
                                        <Money value={book.closing} size="lg" />
                                    }
                                />
                            </div>
                        </SectionCard>

                        {balances.length > 1 && (
                            <SectionCard
                                title="Bagaimana saldonya bergerak?"
                                className="md:col-span-2 print:hidden"
                            >
                                <BalanceArea
                                    caption="Saldo akhir tiap hari"
                                    data={balances}
                                    opening={book.opening}
                                />
                            </SectionCard>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

CashBook.layout = {
    breadcrumbs: [
        { title: 'Laporan', href: reports.index() },
        { title: 'Buku kas', href: reports.cashBook() },
    ],
};
