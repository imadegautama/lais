import { Head } from '@inertiajs/react';
import { CircleCheck, TriangleAlert } from 'lucide-react';
import { LabelBadge } from '@/components/app/badges';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { PrintHeader } from '@/features/reports/print-header';
import { ReportToolbar } from '@/features/reports/report-toolbar';
import { Statement, type StatementRow } from '@/features/reports/statement';
import { cashStepLabel } from '@/features/reports/vocabulary';
import { formatRupiah } from '@/lib/format';
import reports from '@/routes/reports';
import type { EquityProps } from '@/types/domain';

export default function Equity(props: EquityProps) {
    const { report, reconciliation, period, business } = props;
    const scope = period.label.toLowerCase();
    const equityRows: StatementRow[] = [
        { label: 'Modal awal', amount: report.opening, term: 'openingEquity' },
        { label: 'Setoran modal', amount: report.deposits, term: 'deposits' },
        {
            label: report.netProfit < 0 ? 'Rugi bersih' : 'Laba bersih',
            amount: report.netProfit,
            term: 'netProfit',
        },
        {
            label: 'Diambil untuk pribadi',
            amount: -report.drawings,
            term: 'drawings',
        },
        { label: 'Modal akhir', amount: report.closing, level: 'total' },
    ];
    const cashRows: StatementRow[] = [
        {
            label: reconciliation.netProfit < 0 ? 'Rugi bersih' : 'Laba bersih',
            amount: reconciliation.netProfit,
            term: 'netProfit',
        },
        ...reconciliation.steps.map((step): StatementRow => ({
            label: cashStepLabel(step.key, step.amount),
            amount: step.amount,
        })),
        {
            label: 'Perubahan kas',
            amount: reconciliation.cashChange,
            term: 'cashChange',
            level: 'total',
        },
    ];
    const reconciled = reconciliation.difference === 0;

    return (
        <>
            <Head title="Perubahan modal" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PrintHeader
                    business={business.name}
                    title="Perubahan modal"
                    scope={period.label}
                />
                <PageHeader
                    className="print:hidden"
                    title="Perubahan modal"
                    description={`Bagaimana bagian pemilik bertambah atau berkurang ${scope}.`}
                    actions={<ReportToolbar frame={props} />}
                />

                <div className="grid grid-cols-1 items-start gap-4 md:gap-6 lg:grid-cols-2">
                    <SectionCard title={`Perubahan modal ${scope}`}>
                        <Statement rows={equityRows} />
                    </SectionCard>

                    <SectionCard
                        title="Laba vs kas"
                        description="Kenapa uang di kas tidak naik atau turun sebesar laba."
                        className="print:break-before-page"
                    >
                        <Statement rows={cashRows} />
                        <p className="mt-3 flex items-center gap-2 text-sm">
                            {reconciled ? (
                                <LabelBadge
                                    icon={CircleCheck}
                                    label="Selisih Rp0"
                                    tone="success"
                                />
                            ) : (
                                <LabelBadge
                                    icon={TriangleAlert}
                                    label={`Selisih ${formatRupiah(reconciliation.difference)}`}
                                    tone="danger"
                                />
                            )}
                            <span className="text-muted-foreground">
                                {reconciled
                                    ? 'Semua perubahan kas terjelaskan.'
                                    : 'Ada perubahan kas yang belum terjelaskan.'}
                            </span>
                        </p>
                    </SectionCard>
                </div>
            </div>
        </>
    );
}

Equity.layout = {
    breadcrumbs: [
        { title: 'Laporan', href: reports.index() },
        { title: 'Perubahan modal', href: reports.equity() },
    ],
};
