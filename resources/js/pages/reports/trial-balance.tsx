import { Head, Link } from '@inertiajs/react';
import { CalendarX2, CircleCheck, Sigma, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import {
    AppDataTable,
    type DataTableColumn,
} from '@/components/app/app-data-table';
import { LabelBadge } from '@/components/app/badges';
import { SearchFilter } from '@/components/app/filter-bar';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PrintHeader } from '@/features/reports/print-header';
import { ReportLead } from '@/features/reports/report-lead';
import {
    ReportToolbar,
    useReportQuery,
} from '@/features/reports/report-toolbar';
import { TermHint } from '@/components/app/term-hint';
import { formatDate } from '@/lib/format';
import { show as onboarding } from '@/routes/onboarding';
import reports from '@/routes/reports';
import type { ChartAccount, TrialBalanceProps } from '@/types/domain';

type BalanceRow = Pick<ChartAccount, 'code' | 'name' | 'debit' | 'credit'>;

function AccountName({ row }: { row: BalanceRow }) {
    return row.code === '' ? (
        <span className="font-semibold">{row.name}</span>
    ) : (
        <Link
            href={reports.ledger(row.code)}
            className="block rounded-sm hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none pointer-coarse:-my-3 pointer-coarse:py-3"
        >
            <span className="num block text-xs text-muted-foreground">
                {row.code}
            </span>
            {row.name}
        </Link>
    );
}

const amount = (value: number, row: BalanceRow) =>
    value > 0 && (
        <Money
            value={value}
            size="sm"
            className={row.code === '' ? 'font-semibold' : undefined}
        />
    );

const columns: Array<DataTableColumn<BalanceRow>> = [
    {
        id: 'akun',
        header: 'Akun',
        enableSorting: false,
        cell: ({ row }) => <AccountName row={row.original} />,
    },
    {
        id: 'debit',
        header: () => (
            <span className="inline-flex items-center gap-1">
                Debit <TermHint term="debit" label="Debit" />
            </span>
        ),
        enableSorting: false,
        cell: ({ row }) => amount(row.original.debit, row.original),
    },
    {
        id: 'kredit',
        header: () => (
            <span className="inline-flex items-center gap-1">
                Kredit <TermHint term="credit" label="Kredit" />
            </span>
        ),
        enableSorting: false,
        cell: ({ row }) => amount(row.original.credit, row.original),
    },
];

function AccountItem({ row }: { row: BalanceRow }) {
    return (
        <div className="flex items-start justify-between gap-3 text-sm">
            <div className="min-w-0 flex-1">
                <AccountName row={row} />
            </div>
            <div className="flex shrink-0 flex-col items-end gap-1">
                {[
                    { label: 'Debit', value: row.debit },
                    { label: 'Kredit', value: row.credit },
                ]
                    .filter((side) => side.value > 0)
                    .map((side) => (
                        <span key={side.label} className="text-right">
                            {amount(side.value, row)}
                            <span className="block text-xs text-muted-foreground">
                                {side.label}
                            </span>
                        </span>
                    ))}
            </div>
        </div>
    );
}

/** Neraca saldo: every account's debit or credit, adding up to the same total. */
export default function TrialBalance({
    accounts,
    date,
    today,
    startedOn,
    business,
    story,
}: TrialBalanceProps) {
    const report = useReportQuery();
    const day = formatDate(`${date}T12:00:00`);
    const [search, setSearch] = useState('');
    const debit = accounts.reduce((sum, row) => sum + row.debit, 0);
    const credit = accounts.reduce((sum, row) => sum + row.credit, 0);
    const wanted = search.trim().toLowerCase();
    const shown: BalanceRow[] = accounts.filter(
        (row) =>
            wanted === '' ||
            row.name.toLowerCase().includes(wanted) ||
            row.code.startsWith(wanted),
    );
    // Totals close the full list; on a search result they would add up nothing shown.
    const rows =
        wanted === ''
            ? [...shown, { code: '', name: 'Total', debit, credit }]
            : shown;

    return (
        <>
            <Head title="Neraca saldo" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PrintHeader
                    business={business.name}
                    title="Neraca saldo"
                    scope={`per ${day}`}
                />
                <PageHeader
                    className="print:hidden"
                    title="Neraca saldo"
                    description="Saldo semua akun per tanggal. Total debit dan kredit harus sama."
                    meta={
                        accounts.length > 0 &&
                        (debit === credit ? (
                            <LabelBadge
                                icon={CircleCheck}
                                label="Seimbang"
                                tone="success"
                                className="text-sm"
                            />
                        ) : (
                            <LabelBadge
                                icon={TriangleAlert}
                                label="Tidak seimbang"
                                tone="danger"
                                className="text-sm"
                            />
                        ))
                    }
                    actions={
                        <ReportToolbar report={report}>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="saldo-tanggal">
                                    Per tanggal
                                </Label>
                                <Input
                                    id="saldo-tanggal"
                                    type="date"
                                    value={date}
                                    max={today}
                                    min={startedOn ?? undefined}
                                    disabled={report.loading}
                                    onChange={(event) =>
                                        event.target.value &&
                                        report.visit({
                                            tanggal: event.target.value,
                                        })
                                    }
                                    className="w-44"
                                />
                            </div>
                        </ReportToolbar>
                    }
                />

                {accounts.length > 0 && <ReportLead story={story} />}

                {accounts.length === 0 ? (
                    <EmptyState
                        variant="page"
                        icon={CalendarX2}
                        title={`Belum ada saldo per ${day}`}
                        description="Neraca saldo terisi sendiri setelah pembukuan dibuka."
                        action={
                            startedOn === null ? (
                                <Button asChild>
                                    <Link href={onboarding()}>
                                        Buka pembukuan
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    onClick={() =>
                                        report.visit({ tanggal: today })
                                    }
                                >
                                    Lihat saldo hari ini
                                </Button>
                            )
                        }
                    />
                ) : (
                    <SectionCard title={`Per ${day}`}>
                        <div className="mb-3 print:hidden">
                            <SearchFilter
                                placeholder="Cari nama atau kode akun"
                                value={search}
                                onChange={setSearch}
                            />
                        </div>
                        <AppDataTable
                            caption="Neraca saldo"
                            columns={columns}
                            data={rows}
                            getRowId={(row) => row.code || 'total'}
                            layout={{
                                debit: { align: 'right', className: 'w-40' },
                                kredit: { align: 'right', className: 'w-40' },
                            }}
                            renderMobileItem={(row) => (
                                <AccountItem row={row} />
                            )}
                            empty={
                                <EmptyState
                                    icon={Sigma}
                                    title="Tidak ada akun yang cocok"
                                    description="Coba nama atau kode lain."
                                    action={
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setSearch('')}
                                        >
                                            Tampilkan semua akun
                                        </Button>
                                    }
                                />
                            }
                        />
                    </SectionCard>
                )}
            </div>
        </>
    );
}

TrialBalance.layout = {
    breadcrumbs: [
        { title: 'Laporan', href: reports.index() },
        { title: 'Neraca saldo', href: reports.trialBalance() },
    ],
};
