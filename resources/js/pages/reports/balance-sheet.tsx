import { Head } from '@inertiajs/react';
import { CalendarX2, CircleCheck, TriangleAlert } from 'lucide-react';
import { LabelBadge } from '@/components/app/badges';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PrintHeader } from '@/features/reports/print-header';
import {
    ReportToolbar,
    useReportQuery,
} from '@/features/reports/report-toolbar';
import { Statement, type StatementRow } from '@/features/reports/statement';
import { formatDate } from '@/lib/format';
import reports from '@/routes/reports';
import type { BalanceSheetProps } from '@/types/domain';

export default function BalanceSheet({
    report,
    date,
    today,
    startedOn,
    business,
}: BalanceSheetProps) {
    const { visit, loading } = useReportQuery();
    const day = formatDate(`${date}T12:00:00`);
    const beforeBooks = startedOn !== null && date < startedOn;
    const { assets, liabilities, equity } = report;
    const assetRows: StatementRow[] = [
        { label: 'Kas tunai', amount: assets.cash },
        { label: 'Kas non-tunai', amount: assets.nonCash },
        {
            label: 'Piutang usaha',
            amount: assets.receivables,
            term: 'receivables',
        },
        { label: 'Persediaan', amount: assets.inventory, term: 'inventory' },
        { label: 'Peralatan', amount: assets.equipment, term: 'equipment' },
        { label: 'Total aset', amount: assets.total, level: 'total' },
    ];
    const claimRows: StatementRow[] = [
        {
            label: 'Utang usaha',
            amount: liabilities.payables,
            term: 'payables',
        },
        {
            label: 'Uang muka pesanan',
            amount: liabilities.customerDeposits,
            term: 'customerDeposits',
        },
        {
            label: 'Total kewajiban',
            amount: liabilities.total,
            level: 'subtotal',
        },
        { label: 'Modal pemilik', amount: equity.capital, term: 'capital' },
        {
            label: 'Diambil untuk pribadi',
            amount: equity.drawings,
            term: 'drawings',
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

    return (
        <>
            <Head title="Neraca" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PrintHeader
                    business={business.name}
                    title="Neraca"
                    scope={`per ${day}`}
                />
                <PageHeader
                    className="print:hidden"
                    title="Neraca"
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
                        <ReportToolbar>
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
                                    disabled={loading}
                                    onChange={(event) =>
                                        event.target.value &&
                                        visit({ tanggal: event.target.value })
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
                        description="Neraca sebelum tanggal itu masih kosong. Pilih tanggal sejak pembukuan dibuka."
                    />
                ) : (
                    <>
                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2 md:gap-6">
                            <SectionCard title="Yang dimiliki usaha">
                                <Statement rows={assetRows} />
                            </SectionCard>
                            <SectionCard title="Utang dan bagian pemilik">
                                <Statement rows={claimRows} />
                            </SectionCard>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Peralatan dicatat sebesar harga beli, tanpa
                            penyusutan.
                        </p>
                    </>
                )}
            </div>
        </>
    );
}

BalanceSheet.layout = {
    breadcrumbs: [
        { title: 'Laporan', href: reports.index() },
        { title: 'Neraca', href: reports.balanceSheet() },
    ],
};
