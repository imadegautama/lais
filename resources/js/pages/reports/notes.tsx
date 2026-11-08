import { Head } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    BreakdownBars,
    type BreakdownRow,
} from '@/features/reports/breakdown-bars';
import { PrintHeader } from '@/features/reports/print-header';
import { ReportToolbar } from '@/features/reports/report-toolbar';
import { accountingPolicies } from '@/features/reports/vocabulary';
import { formatDate, formatQty, formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';
import reports from '@/routes/reports';
import type { NotesProps } from '@/types/domain';
import {
    transactionKindExpenseAccounts,
    transactionKindLabels,
    type TransactionKind,
} from '@/types/enums';

type Note = {
    number: number;
    title: string;
    total: number;
    rows: BreakdownRow[];
    href?: string;
};

function Policy({ title, body }: { title: string; body: string }) {
    const [open, setOpen] = useState(false);

    return (
        <Collapsible
            open={open}
            onOpenChange={setOpen}
            className="break-inside-avoid border-b last:border-b-0"
        >
            <CollapsibleTrigger className="flex min-h-11 w-full items-center justify-between gap-3 rounded-md py-2 text-left text-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                {title}
                <ChevronDown
                    className={cn(
                        'size-4 shrink-0 text-muted-foreground transition-transform motion-reduce:transition-none print:hidden',
                        open && 'rotate-180',
                    )}
                    aria-hidden
                />
            </CollapsibleTrigger>
            {/* Kept in the page so paper always carries every policy. */}
            <CollapsibleContent
                forceMount
                className="pb-3 text-sm text-muted-foreground data-[state=closed]:hidden print:block!"
            >
                {body}
            </CollapsibleContent>
        </Collapsible>
    );
}

/**
 * Catatan atas laporan keuangan, the third report of SAK EMKM (par. 3.9, 6.2).
 * Numbered because the Laporan Posisi Keuangan points at these numbers
 * (par. 6.4). A note with nothing in it takes one line, not a card.
 */
export default function Notes(props: NotesProps) {
    const { business, period, category, today, position, details } = props;
    const { expensesByKind, previousExpensesByKind, previousLabel, query } =
        props;
    const day = formatDate(`${today}T12:00:00`);
    const scope = period.key ? period.label.toLowerCase() : period.label;
    const ledger = (code: string) => reports.ledger.url(code, { query });
    const named = (row: { name: string; amount: number }): BreakdownRow => ({
        label: row.name,
        amount: row.amount,
    });
    const kinds = [
        ...new Set([
            ...Object.keys(expensesByKind),
            ...Object.keys(previousExpensesByKind),
        ]),
    ] as TransactionKind[];
    const notes: Note[] = [
        {
            number: 1,
            title: 'Kas dan rekening',
            total: position.assets.cash + position.assets.nonCash,
            rows: details.money.filter((row) => row.amount !== 0).map(named),
            href: ledger('1101'),
        },
        {
            number: 2,
            title: 'Piutang usaha',
            total: position.assets.receivables,
            rows: details.receivables.map(named),
            href: ledger('1201'),
        },
        {
            number: 3,
            title: 'Persediaan',
            total: position.assets.inventory,
            rows: [...details.inventory]
                .sort((a, b) => b.amount - a.amount)
                .map((row) => ({
                    label: row.name,
                    amount: row.amount,
                    hint: formatQty(Number(row.qty), row.unit),
                })),
            href: ledger('1301'),
        },
        {
            number: 4,
            title: 'Aset tetap',
            total:
                position.assets.equipment +
                position.assets.accumulatedDepreciation,
            rows: details.equipment.map((row) => ({
                label: row.name,
                amount: row.bookValue,
                hint: `Harga ${formatRupiah(row.cost)}, disusutkan ${formatRupiah(row.accumulated)}`,
            })),
            href: ledger('1401'),
        },
        {
            number: 5,
            title: 'Utang usaha',
            total: position.liabilities.payables,
            rows: details.payables.map(named),
            href: ledger('2101'),
        },
        {
            number: 6,
            title: `Biaya operasional ${scope}`,
            total: kinds.reduce(
                (sum, kind) => sum + (expensesByKind[kind] ?? 0),
                0,
            ),
            rows: kinds.map((kind) => ({
                label: transactionKindLabels[kind],
                amount: expensesByKind[kind] ?? 0,
                hint: `${previousLabel}: ${formatRupiah(previousExpensesByKind[kind] ?? 0)}`,
                href: ledger(transactionKindExpenseAccounts[kind] ?? '6101'),
            })),
        },
    ];
    const filled = notes.filter((note) => note.rows.length > 0);
    const blank = notes.filter((note) => note.rows.length === 0);
    const contents = [
        { href: '#dasar', label: 'Dasar penyusunan' },
        { href: '#kebijakan', label: 'Kebijakan akuntansi' },
        ...filled.map((note) => ({
            href: `#catatan-${note.number}`,
            label: `${note.number}. ${note.title}`,
        })),
    ];

    return (
        <>
            <Head title="Catatan laporan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PrintHeader
                    business={business.name}
                    title="Catatan atas laporan keuangan"
                    scope={`per ${day}`}
                />
                <PageHeader
                    className="print:hidden"
                    title="Catatan atas laporan keuangan"
                    description={`Rincian akun per ${day}, dan biaya ${scope}.`}
                    actions={<ReportToolbar frame={props} />}
                />

                <div className="grid grid-cols-1 items-start gap-4 md:gap-6 xl:grid-cols-12">
                    <div className="flex min-w-0 flex-col gap-4 md:gap-6 xl:col-span-9">
                        <div id="dasar" className="scroll-mt-20">
                            <SectionCard title="Dasar penyusunan">
                                <div className="flex max-w-prose flex-col gap-2 text-sm">
                                    <p>
                                        Laporan keuangan {business.name}
                                        {category &&
                                            ` (usaha ${category.toLowerCase()})`}{' '}
                                        disusun mengacu pada Standar Akuntansi
                                        Keuangan Entitas Mikro, Kecil, dan
                                        Menengah (SAK EMKM).
                                    </p>
                                    <p className="text-muted-foreground">
                                        Laporan memakai dasar akrual dan asumsi
                                        kelangsungan usaha. Keuangan usaha
                                        dicatat terpisah dari keuangan pribadi
                                        pemilik. Semua angka dalam Rupiah penuh.
                                    </p>
                                    {blank.length > 0 && (
                                        <p className="text-muted-foreground">
                                            Tidak ada saldo untuk{' '}
                                            {blank
                                                .map((note) =>
                                                    note.title.toLowerCase(),
                                                )
                                                .join(', ')}
                                            .
                                        </p>
                                    )}
                                </div>
                            </SectionCard>
                        </div>

                        <div id="kebijakan" className="scroll-mt-20">
                            <SectionCard
                                title="Kebijakan akuntansi"
                                description={
                                    <span className="print:hidden">
                                        Tekan untuk membaca aturan
                                        pencatatannya.
                                    </span>
                                }
                            >
                                <div className="columns-1 gap-8 md:columns-2">
                                    {accountingPolicies.map((policy) => (
                                        <Policy
                                            key={policy.title}
                                            title={policy.title}
                                            body={policy.body}
                                        />
                                    ))}
                                </div>
                            </SectionCard>
                        </div>

                        {/* Columns, not a grid: a short note never leaves a hole beside a long one. */}
                        <div className="columns-1 gap-4 md:columns-2 md:gap-6">
                            {filled.map((note) => (
                                <div
                                    key={note.number}
                                    id={`catatan-${note.number}`}
                                    className="mb-4 scroll-mt-20 break-inside-avoid md:mb-6"
                                >
                                    <SectionCard
                                        title={`${note.number}. ${note.title}`}
                                    >
                                        <BreakdownBars
                                            label={note.title}
                                            rows={note.rows}
                                        />
                                        <div className="mt-3 flex items-baseline justify-between gap-3 border-t-2 border-foreground/80 pt-3 font-semibold">
                                            <span>Jumlah</span>
                                            <Money
                                                value={note.total}
                                                size="lg"
                                            />
                                        </div>
                                    </SectionCard>
                                </div>
                            ))}
                        </div>
                    </div>

                    <nav
                        aria-label="Isi catatan"
                        className="sticky top-20 hidden xl:col-span-3 xl:block print:hidden"
                    >
                        <SectionCard title="Isi catatan">
                            <ul className="flex flex-col gap-0.5 text-sm">
                                {contents.map((item) => (
                                    <li key={item.href}>
                                        <a
                                            href={item.href}
                                            className="block rounded-md px-2 py-1.5 text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none pointer-coarse:py-3"
                                        >
                                            {item.label}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </SectionCard>
                    </nav>
                </div>
            </div>
        </>
    );
}

Notes.layout = {
    breadcrumbs: [
        { title: 'Laporan', href: reports.index() },
        { title: 'Catatan laporan', href: reports.notes() },
    ],
};
