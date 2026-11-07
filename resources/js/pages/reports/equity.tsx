import { Head } from '@inertiajs/react';
import { CircleCheck, TriangleAlert } from 'lucide-react';
import { LabelBadge } from '@/components/app/badges';
import { MetricInline } from '@/components/app/metric';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { BreakdownBars } from '@/features/reports/breakdown-bars';
import { PrintHeader } from '@/features/reports/print-header';
import { ReportLead } from '@/features/reports/report-lead';
import { ReportToolbar } from '@/features/reports/report-toolbar';
import { Statement, type StatementRow } from '@/features/reports/statement';
import { cashStepLabel } from '@/features/reports/vocabulary';
import { formatRupiah } from '@/lib/format';
import reports from '@/routes/reports';
import type { EquityProps } from '@/types/domain';

export default function Equity(props: EquityProps) {
    const { report, reconciliation, period, business, query, story } = props;
    const scope = period.key ? period.label.toLowerCase() : period.label;
    const ledger = (code: string) => reports.ledger.url(code, { query });
    const equityRows: StatementRow[] = [
        { label: 'Modal awal', amount: report.opening, term: 'openingEquity' },
        {
            label: 'Setoran modal',
            amount: report.deposits,
            term: 'deposits',
            href: ledger('3101'),
        },
        {
            label: report.netProfit < 0 ? 'Rugi bersih' : 'Laba bersih',
            amount: report.netProfit,
            term: 'netProfit',
            href: reports.profitLoss.url({ query }),
        },
        {
            label: 'Diambil untuk pribadi',
            amount: -report.drawings,
            term: 'drawings',
            href: ledger('3201'),
        },
        { label: 'Modal akhir', amount: report.closing, level: 'total' },
    ];
    const steps = reconciliation.steps
        .filter((step) => step.amount !== 0)
        .sort((a, b) => Math.abs(b.amount) - Math.abs(a.amount));
    const cashRows: StatementRow[] = [
        {
            label: reconciliation.netProfit < 0 ? 'Rugi bersih' : 'Laba bersih',
            amount: reconciliation.netProfit,
            term: 'netProfit',
        },
        ...steps.map((step): StatementRow => ({
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
    const change = report.closing - report.opening;
    const movements = [
        { label: 'Laba bersih', amount: report.netProfit },
        { label: 'Setoran modal', amount: report.deposits },
        { label: 'Diambil untuk pribadi', amount: -report.drawings },
    ]
        .filter((row) => row.amount !== 0)
        .sort((a, b) => Math.abs(b.amount) - Math.abs(a.amount));

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

                <ReportLead story={story} />

                <div className="grid grid-cols-1 gap-4 md:gap-6 lg:grid-cols-12">
                    <div className="flex min-w-0 flex-col gap-4 md:gap-6 lg:col-span-5">
                        <SectionCard title="Laporan perubahan modal">
                            <Statement rows={equityRows} />
                        </SectionCard>

                        {movements.length > 0 && (
                            <SectionCard
                                title="Apa yang mengubah modal?"
                                className="flex-1 print:hidden"
                            >
                                <p className="mb-3 text-sm">
                                    Modal {change < 0 ? 'turun' : 'naik'}{' '}
                                    <Money
                                        value={Math.abs(change)}
                                        size="sm"
                                        className="font-semibold"
                                    />{' '}
                                    dari {formatRupiah(report.opening)}.
                                </p>
                                <BreakdownBars
                                    label="Yang mengubah modal"
                                    rows={movements}
                                />
                            </SectionCard>
                        )}
                    </div>

                    <SectionCard
                        title="Kenapa laba tidak sama dengan kas?"
                        description="Laba dihitung dari penjualan dan biaya. Kas dari uang yang benar-benar masuk dan keluar."
                        className="lg:col-span-7 print:break-before-page"
                    >
                        <div className="print:hidden">
                            <div className="flex flex-col divide-y">
                                <MetricInline
                                    label={
                                        reconciliation.netProfit < 0
                                            ? 'Rugi bersih'
                                            : 'Laba bersih'
                                    }
                                    value={
                                        <Money
                                            value={reconciliation.netProfit}
                                        />
                                    }
                                />
                            </div>
                            {steps.length > 0 && (
                                <div className="mt-3">
                                    <BreakdownBars
                                        label="Yang membuat laba dan kas berbeda"
                                        rows={steps.map((step) => ({
                                            label: cashStepLabel(
                                                step.key,
                                                step.amount,
                                            ),
                                            amount: step.amount,
                                        }))}
                                    />
                                </div>
                            )}
                            <div className="mt-3 flex items-baseline justify-between gap-3 border-t-2 border-foreground/80 pt-3 font-semibold">
                                <span>Perubahan kas</span>
                                <Money
                                    value={reconciliation.cashChange}
                                    size="lg"
                                />
                            </div>
                        </div>
                        <div className="hidden print:block">
                            <Statement rows={cashRows} />
                        </div>
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
