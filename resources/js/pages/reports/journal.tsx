import { Head, Link } from '@inertiajs/react';
import { NotebookText } from 'lucide-react';
import { useState } from 'react';
import {
    AppDataTable,
    type DataTableColumn,
} from '@/components/app/app-data-table';
import { SearchFilter } from '@/components/app/filter-bar';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { PrintHeader } from '@/features/reports/print-header';
import { ReportLead } from '@/features/reports/report-lead';
import { ReportToolbar } from '@/features/reports/report-toolbar';
import { TermHint } from '@/features/reports/term-hint';
import { usePrinting } from '@/features/reports/use-printing';
import { formatDate } from '@/lib/format';
import reports from '@/routes/reports';
import {
    create as createTransaction,
    show as showTransaction,
} from '@/routes/transactions';
import type { JournalEntryRow, JournalProps } from '@/types/domain';

function Description({ entry }: { entry: JournalEntryRow }) {
    return entry.transactionId === null ? (
        <span className="font-medium whitespace-normal">
            {entry.description}
        </span>
    ) : (
        <Link
            href={showTransaction(entry.transactionId)}
            className="rounded-sm font-medium whitespace-normal hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none pointer-coarse:inline-block pointer-coarse:py-3"
        >
            {entry.description}
        </Link>
    );
}

const amounts = (entry: JournalEntryRow, side: 'debit' | 'credit') => (
    <span className="flex flex-col gap-1">
        {entry.lines.map((line, index) => (
            <span key={index} className="h-6">
                {line[side] > 0 && <Money value={line[side]} size="sm" />}
            </span>
        ))}
    </span>
);

const columns: Array<DataTableColumn<JournalEntryRow>> = [
    {
        id: 'tanggal',
        accessorKey: 'id',
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
        cell: ({ row }) => <Description entry={row.original} />,
    },
    {
        id: 'akun',
        header: 'Akun',
        enableSorting: false,
        cell: ({ row }) => (
            <span className="flex flex-col gap-1">
                {row.original.lines.map((line, index) => (
                    <span
                        key={index}
                        // A credit line sits indented, the way a journal is written by hand.
                        className={`h-6 whitespace-nowrap ${line.credit > 0 ? 'pl-5' : ''}`}
                    >
                        <span className="num text-muted-foreground">
                            {line.code}
                        </span>{' '}
                        {line.name}
                    </span>
                ))}
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
        cell: ({ row }) => amounts(row.original, 'debit'),
    },
    {
        id: 'kredit',
        header: () => (
            <span className="inline-flex items-center gap-1">
                Kredit <TermHint term="credit" label="Kredit" />
            </span>
        ),
        enableSorting: false,
        cell: ({ row }) => amounts(row.original, 'credit'),
    },
];

function EntryItem({ entry }: { entry: JournalEntryRow }) {
    return (
        <div className="flex flex-col gap-1.5 text-sm">
            <p>
                <Description entry={entry} />
            </p>
            <p className="text-xs text-muted-foreground">
                {formatDate(entry.occurredAt, 'time')}
            </p>
            <ul className="flex flex-col gap-1">
                {entry.lines.map((line, index) => (
                    <li
                        key={index}
                        className="flex items-baseline justify-between gap-3"
                    >
                        <span className={line.credit > 0 ? 'pl-4' : ''}>
                            {line.name}
                            <span className="ml-1 text-xs text-muted-foreground">
                                {line.debit > 0 ? 'Debit' : 'Kredit'}
                            </span>
                        </span>
                        <Money value={line.debit + line.credit} size="sm" />
                    </li>
                ))}
            </ul>
        </div>
    );
}

/** Jurnal umum: every entry behind the reports, in the order it was booked. */
export default function Journal(props: JournalProps) {
    const { entries, story, period, business } = props;
    const printing = usePrinting();
    const [search, setSearch] = useState('');
    const scope = period.key ? period.label.toLowerCase() : period.label;
    const wanted = search.trim().toLowerCase();
    const shown = entries.filter(
        (entry) =>
            wanted === '' ||
            entry.description.toLowerCase().includes(wanted) ||
            entry.lines.some((line) =>
                line.name.toLowerCase().includes(wanted),
            ),
    );
    const total = (side: 'debit' | 'credit') =>
        entries.reduce(
            (sum, entry) =>
                sum + entry.lines.reduce((all, line) => all + line[side], 0),
            0,
        );

    return (
        <>
            <Head title="Jurnal umum" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PrintHeader
                    business={business.name}
                    title="Jurnal umum"
                    scope={period.label}
                />
                <PageHeader
                    className="print:hidden"
                    title="Jurnal umum"
                    description={`Semua pencatatan ${scope}, urut tanggal.`}
                    actions={<ReportToolbar frame={props} />}
                />

                {entries.length > 0 && <ReportLead story={story} />}

                <SectionCard title="Jurnal">
                    {entries.length > 0 && (
                        <div className="mb-3 print:hidden">
                            <SearchFilter
                                placeholder="Cari keterangan atau akun"
                                value={search}
                                onChange={setSearch}
                            />
                        </div>
                    )}

                    <AppDataTable
                        key={printing ? 'kertas' : 'layar'}
                        caption="Jurnal umum"
                        columns={columns}
                        data={shown}
                        getRowId={(entry) => String(entry.id)}
                        initialSorting={[{ id: 'tanggal', desc: true }]}
                        pageSize={printing ? undefined : 20}
                        layout={{
                            tanggal: { className: 'w-28 [&:is(td)]:align-top' },
                            keterangan: { className: '[&:is(td)]:align-top' },
                            akun: { className: '[&:is(td)]:align-top' },
                            debit: {
                                align: 'right',
                                className: 'w-32 [&:is(td)]:align-top',
                            },
                            kredit: {
                                align: 'right',
                                className: 'w-32 [&:is(td)]:align-top',
                            },
                        }}
                        renderMobileItem={(entry) => (
                            <EntryItem entry={entry} />
                        )}
                        mobileGroupBy={(entry) =>
                            formatDate(entry.occurredAt, 'long')
                        }
                        empty={
                            entries.length === 0 ? (
                                <EmptyState
                                    icon={NotebookText}
                                    title={`Belum ada jurnal ${scope}`}
                                    description="Jurnal terisi sendiri setiap ada transaksi."
                                    action={
                                        <Button asChild>
                                            <Link href={createTransaction()}>
                                                Catat transaksi
                                            </Link>
                                        </Button>
                                    }
                                />
                            ) : (
                                <EmptyState
                                    icon={NotebookText}
                                    title="Tidak ada jurnal yang cocok"
                                    description="Coba kata lain."
                                    action={
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setSearch('')}
                                        >
                                            Tampilkan semua jurnal
                                        </Button>
                                    }
                                />
                            )
                        }
                    />

                    {entries.length > 0 && (
                        <div className="mt-3 flex flex-wrap items-baseline justify-end gap-x-8 gap-y-1 border-t-2 border-foreground/80 pt-3 font-semibold">
                            <span className="flex items-baseline gap-2">
                                Total debit
                                <Money value={total('debit')} size="md" />
                            </span>
                            <span className="flex items-baseline gap-2">
                                Total kredit
                                <Money value={total('credit')} size="md" />
                            </span>
                        </div>
                    )}
                </SectionCard>
            </div>
        </>
    );
}

Journal.layout = {
    breadcrumbs: [
        { title: 'Laporan', href: reports.index() },
        { title: 'Jurnal umum', href: reports.journal() },
    ],
};
