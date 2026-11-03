import { Head, Link } from '@inertiajs/react';
import { NotebookText, TrendingDown } from 'lucide-react';
import { LabelBadge } from '@/components/app/badges';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { PrintHeader } from '@/features/reports/print-header';
import { ReportToolbar } from '@/features/reports/report-toolbar';
import { Statement, type StatementRow } from '@/features/reports/statement';
import reports from '@/routes/reports';
import { create } from '@/routes/transactions';
import type { ProfitAndLossProps } from '@/types/domain';
import { transactionKindLabels, type TransactionKind } from '@/types/enums';

export default function ProfitAndLoss(props: ProfitAndLossProps) {
    const { report, period, business } = props;
    const loss = report.netProfit < 0;
    const empty =
        report.sales === 0 &&
        report.expenses === 0 &&
        report.cogs === 0 &&
        report.stockDifference === 0;
    const rows: StatementRow[] = [
        { label: 'Penjualan', amount: report.sales, term: 'sales' },
        { label: 'HPP', amount: -report.cogs, term: 'cogs' },
        {
            label: 'Laba kotor',
            amount: report.grossProfit,
            term: 'grossProfit',
            level: 'subtotal',
        },
        {
            label: 'Biaya operasional',
            amount: -report.expenses,
            term: 'expenses',
        },
        ...(
            Object.entries(report.expensesByKind) as Array<
                [TransactionKind, number]
            >
        ).map(([kind, amount]): StatementRow => ({
            label: transactionKindLabels[kind],
            amount: -amount,
            level: 'detail',
        })),
        {
            label: 'Selisih persediaan',
            amount: -report.stockDifference,
            term: 'stockDifference',
        },
        {
            label: loss ? 'Rugi bersih' : 'Laba bersih',
            amount: report.netProfit,
            term: 'netProfit',
            level: 'total',
            note: loss && (
                <LabelBadge icon={TrendingDown} label="Rugi" tone="danger" />
            ),
        },
    ];

    return (
        <>
            <Head title="Laba rugi" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PrintHeader
                    business={business.name}
                    title="Laba rugi"
                    scope={period.label}
                />
                <PageHeader
                    className="print:hidden"
                    title="Laba rugi"
                    description={`Untung atau rugi usaha ${period.label.toLowerCase()}, dari semua penjualan dan biaya.`}
                    actions={<ReportToolbar frame={props} />}
                />

                <SectionCard
                    title={`Laba rugi ${period.label.toLowerCase()}`}
                    className="max-w-2xl"
                >
                    {empty ? (
                        <EmptyState
                            icon={NotebookText}
                            title={`Belum ada penjualan atau biaya ${period.label.toLowerCase()}`}
                            description="Laporan ini terisi sendiri dari catatan transaksi."
                            action={
                                <Button asChild variant="outline">
                                    <Link href={create()}>Catat transaksi</Link>
                                </Button>
                            }
                        />
                    ) : (
                        <Statement rows={rows} />
                    )}
                </SectionCard>
            </div>
        </>
    );
}

ProfitAndLoss.layout = {
    breadcrumbs: [
        { title: 'Laporan', href: reports.index() },
        { title: 'Laba rugi', href: reports.profitLoss() },
    ],
};
