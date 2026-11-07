import { Head, Link } from '@inertiajs/react';
import { CalendarX2, CircleCheck, TriangleAlert } from 'lucide-react';
import { LabelBadge } from '@/components/app/badges';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    BreakdownBars,
    type BreakdownRow,
} from '@/features/reports/breakdown-bars';
import { PrintHeader } from '@/features/reports/print-header';
import { ReportLead } from '@/features/reports/report-lead';
import {
    ReportToolbar,
    useReportQuery,
} from '@/features/reports/report-toolbar';
import {
    Statement,
    withPrevious,
    type StatementRow,
} from '@/features/reports/statement';
import { formatDate } from '@/lib/format';
import reports from '@/routes/reports';
import type { BalanceSheetProps } from '@/types/domain';

const TITLE = 'Laporan Posisi Keuangan (Neraca)';

export default function BalanceSheet({
    report,
    previous,
    date,
    previousDate,
    today,
    startedOn,
    business,
    story,
}: BalanceSheetProps) {
    const reload = useReportQuery();
    const day = formatDate(`${date}T12:00:00`);
    const columns = {
        current: day,
        previous: formatDate(`${previousDate}T12:00:00`),
    };
    const beforeBooks = startedOn !== null && date < startedOn;
    const ledger = (code: string) => reports.ledger.url(code);
    const note = (number: number) => ({
        label: `Catatan ${number}`,
        href: `${reports.notes.url()}#catatan-${number}`,
    });
    const assetLines = ({ assets }: typeof report): StatementRow[] => [
        {
            label: 'Kas tunai',
            amount: assets.cash,
            href: ledger('1101'),
            reference: note(1),
        },
        {
            label: 'Kas non-tunai',
            amount: assets.nonCash,
            href: ledger('1102'),
            reference: note(1),
        },
        {
            label: 'Piutang usaha',
            amount: assets.receivables,
            term: 'receivables',
            href: ledger('1201'),
            reference: note(2),
        },
        {
            label: 'Persediaan',
            amount: assets.inventory,
            term: 'inventory',
            href: ledger('1301'),
            reference: note(3),
        },
        {
            label: 'Peralatan',
            amount: assets.equipment,
            term: 'equipment',
            href: ledger('1401'),
            reference: note(4),
        },
        {
            label: 'Akumulasi penyusutan',
            amount: assets.accumulatedDepreciation,
            term: 'accumulatedDepreciation',
            href: ledger('1402'),
        },
        { label: 'Total aset', amount: assets.total, level: 'total' },
    ];
    const claimLines = ({
        liabilities,
        equity,
    }: typeof report): StatementRow[] => [
        {
            label: 'Utang usaha',
            amount: liabilities.payables,
            term: 'payables',
            href: ledger('2101'),
            reference: note(5),
        },
        {
            label: 'Uang muka pesanan',
            amount: liabilities.customerDeposits,
            term: 'customerDeposits',
            href: ledger('2201'),
        },
        {
            label: 'Total kewajiban',
            amount: liabilities.total,
            level: 'subtotal',
        },
        {
            label: 'Modal pemilik',
            amount: equity.capital,
            term: 'capital',
            href: ledger('3101'),
        },
        {
            label: 'Diambil untuk pribadi',
            amount: equity.drawings,
            term: 'drawings',
            href: ledger('3201'),
        },
        {
            label: 'Laba sampai tanggal ini',
            amount: equity.profit,
            term: 'profitToDate',
        },
        { label: 'Total modal', amount: equity.total, level: 'subtotal' },
        {
            label: 'Total kewajiban dan modal',
            amount: liabilities.total + equity.total,
            level: 'total',
        },
    ];
    const assetRows = withPrevious(assetLines(report), assetLines(previous));
    const claimRows = withPrevious(claimLines(report), claimLines(previous));
    const { assets, liabilities, equity } = report;
    const owned = assets.total > 0 ? (equity.total / assets.total) * 100 : 0;
    const largestFirst = (a: BreakdownRow, b: BreakdownRow) =>
        Math.abs(b.amount) - Math.abs(a.amount);
    const wealth: BreakdownRow[] = [
        {
            label: 'Kas dan rekening',
            amount: assets.cash + assets.nonCash,
            href: ledger('1101'),
        },
        {
            label: 'Piutang',
            amount: assets.receivables,
            href: ledger('1201'),
        },
        {
            label: 'Persediaan',
            amount: assets.inventory,
            href: ledger('1301'),
        },
        {
            label: 'Peralatan',
            amount: assets.equipment + assets.accumulatedDepreciation,
            href: ledger('1401'),
        },
    ].sort(largestFirst);
    const sources: BreakdownRow[] = [
        {
            label: 'Modal dari pemilik',
            amount: equity.capital + equity.drawings,
            href: ledger('3101'),
        },
        { label: 'Laba yang terkumpul', amount: equity.profit },
        {
            label: 'Utang ke supplier',
            amount: liabilities.payables,
            href: ledger('2101'),
        },
        {
            label: 'Uang muka pelanggan',
            amount: liabilities.customerDeposits,
            href: ledger('2201'),
        },
    ].sort(largestFirst);

    return (
        <>
            <Head title="Laporan posisi keuangan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PrintHeader
                    business={business.name}
                    title={TITLE}
                    scope={`per ${day}`}
                />
                <PageHeader
                    className="print:hidden"
                    title={TITLE}
                    description="Apa yang dimiliki usaha, apa yang masih harus dibayar, dan bagian pemilik, per tanggal."
                    meta={
                        !beforeBooks &&
                        (report.balanced ? (
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
                        <ReportToolbar report={reload}>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="neraca-tanggal">
                                    Per tanggal
                                </Label>
                                <Input
                                    id="neraca-tanggal"
                                    type="date"
                                    value={date}
                                    max={today}
                                    min={startedOn ?? undefined}
                                    disabled={reload.loading}
                                    onChange={(event) =>
                                        event.target.value &&
                                        reload.visit({
                                            tanggal: event.target.value,
                                        })
                                    }
                                    className="w-44"
                                />
                            </div>
                        </ReportToolbar>
                    }
                />

                {beforeBooks ? (
                    <EmptyState
                        variant="page"
                        icon={CalendarX2}
                        title={`Pembukuan dibuka ${formatDate(`${startedOn}T12:00:00`)}`}
                        action={
                            <Button
                                type="button"
                                onClick={() => reload.visit({ tanggal: today })}
                            >
                                Lihat posisi hari ini
                            </Button>
                        }
                        description="Laporan sebelum tanggal itu masih kosong. Pilih tanggal sejak pembukuan dibuka."
                    />
                ) : (
                    <>
                        <ReportLead story={story} />

                        <div className="grid grid-cols-1 gap-4 md:gap-6 lg:grid-cols-12">
                            <SectionCard
                                title="Yang dimiliki usaha"
                                className="lg:col-span-7"
                            >
                                <Statement rows={assetRows} columns={columns} />
                            </SectionCard>

                            <SectionCard
                                title="Kekayaan usaha ada di mana?"
                                className="lg:col-span-5 print:hidden"
                            >
                                <p className="mb-3 text-sm">
                                    {assets.total > 0
                                        ? `Paling banyak di ${wealth[0].label.toLowerCase()}, ${Math.round((wealth[0].amount / assets.total) * 100)}% dari total aset.`
                                        : 'Belum ada aset yang tercatat.'}
                                </p>
                                <BreakdownBars
                                    label="Komposisi aset"
                                    rows={wealth}
                                />
                            </SectionCard>

                            <SectionCard
                                title="Utang dan bagian pemilik"
                                className="lg:col-span-7"
                            >
                                <Statement rows={claimRows} columns={columns} />
                            </SectionCard>

                            <SectionCard
                                title="Milik siapa?"
                                className="lg:col-span-5 print:hidden"
                            >
                                <p className="mb-3 text-sm">
                                    {sources[0].amount !== 0
                                        ? `Sumber terbesar: ${sources[0].label.toLowerCase()}.`
                                        : 'Belum ada modal atau utang yang tercatat.'}
                                </p>
                                <div
                                    role="img"
                                    aria-label={`${Math.round(owned)} persen milik pemilik, sisanya kewajiban.`}
                                    className="flex h-2 gap-0.5 overflow-hidden rounded-full"
                                >
                                    <span
                                        className="bg-chart-1"
                                        style={{
                                            width: `${Math.min(Math.max(owned, 0), 100)}%`,
                                        }}
                                    />
                                    <span className="flex-1 bg-chart-2" />
                                </div>
                                <dl className="mt-3 mb-4 grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <dt className="flex items-center gap-1.5 text-muted-foreground">
                                            <span
                                                aria-hidden
                                                className="size-2.5 rounded-sm bg-chart-1"
                                            />
                                            Milik pemilik
                                        </dt>
                                        <dd>
                                            <Money
                                                value={equity.total}
                                                size="md"
                                            />
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="flex items-center gap-1.5 text-muted-foreground">
                                            <span
                                                aria-hidden
                                                className="size-2.5 rounded-sm bg-chart-2"
                                            />
                                            Masih harus dibayar
                                        </dt>
                                        <dd>
                                            <Money
                                                value={liabilities.total}
                                                size="md"
                                            />
                                        </dd>
                                    </div>
                                </dl>
                                <BreakdownBars
                                    label="Asal uang usaha"
                                    highlightFirst={false}
                                    rows={sources}
                                />
                                <p className="mt-3 border-t pt-3 text-sm text-muted-foreground">
                                    Penjelasan tiap pos ada di{' '}
                                    <Link
                                        href={reports.notes()}
                                        className="font-medium text-primary-text underline underline-offset-4"
                                    >
                                        Catatan laporan
                                    </Link>
                                    .
                                </p>
                            </SectionCard>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

BalanceSheet.layout = {
    breadcrumbs: [
        { title: 'Laporan', href: reports.index() },
        { title: 'Posisi keuangan', href: reports.balanceSheet() },
    ],
};
