import { Head, Link } from '@inertiajs/react';
import { NotebookText, TrendingDown } from 'lucide-react';
import { LabelBadge } from '@/components/app/badges';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { BreakdownBars } from '@/features/reports/breakdown-bars';
import { PrintHeader } from '@/features/reports/print-header';
import { ReportLead } from '@/features/reports/report-lead';
import { ReportToolbar } from '@/features/reports/report-toolbar';
import {
    Statement,
    withPrevious,
    type StatementRow,
} from '@/features/reports/statement';
import { MonthlyBars } from '@/features/reports/trend-chart';
import { formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';
import reports from '@/routes/reports';
import { create } from '@/routes/transactions';
import type { ProfitAndLossProps } from '@/types/domain';
import {
    AccountType,
    transactionKindLabels,
    type TransactionKind,
} from '@/types/enums';

export default function ProfitAndLoss(props: ProfitAndLossProps) {
    const { report, previous, previousLabel, period, business, query, story } =
        props;
    const trend = props.trend;
    const scope = period.key ? period.label.toLowerCase() : period.label;
    const loss = report.netProfit < 0;
    const empty =
        report.sales === 0 &&
        report.otherIncome === 0 &&
        report.otherExpenses === 0 &&
        report.depreciation === 0 &&
        report.expenses === 0 &&
        report.cogs === 0 &&
        report.stockDifference === 0;
    const ledger = (code: string) => reports.ledger.url(code, { query });
    // Both periods list the same kinds and accounts, so their lines pair up one to one.
    const kinds = [
        ...new Set([
            ...Object.keys(report.expensesByKind),
            ...Object.keys(previous.expensesByKind),
        ]),
    ] as TransactionKind[];
    const owned = [
        ...new Map(
            [...report.ownAccounts, ...previous.ownAccounts].map((account) => [
                account.code,
                account,
            ]),
        ).values(),
    ].sort((a, b) => a.code.localeCompare(b.code));
    const ownLines = (from: typeof report, type: AccountType) =>
        owned
            .filter((account) => account.type === type)
            .map((account): StatementRow => {
                const amount =
                    from.ownAccounts.find((own) => own.code === account.code)
                        ?.amount ?? 0;

                return {
                    label: account.name,
                    amount: type === AccountType.Beban ? -amount : amount,
                    href: ledger(account.code),
                };
            });
    const lines = (from: typeof report): StatementRow[] => [
        {
            label: 'Penjualan',
            amount: from.sales,
            term: 'sales',
            href: ledger('4101'),
        },
        {
            label: 'HPP',
            amount: -from.cogs,
            term: 'cogs',
            href: ledger('5101'),
        },
        {
            label: 'Laba kotor',
            amount: from.grossProfit,
            term: 'grossProfit',
            level: 'subtotal',
        },
        ...ownLines(from, AccountType.Pendapatan),
        {
            label: 'Biaya operasional',
            amount: -from.expenses,
            term: 'expenses',
            href: ledger('6101'),
            details: kinds.map((kind) => ({
                label: transactionKindLabels[kind],
                amount: -(from.expensesByKind[kind] ?? 0),
            })),
        },
        ...ownLines(from, AccountType.Beban),
        {
            label: 'Beban penyusutan',
            amount: -from.depreciation,
            term: 'depreciation',
            href: ledger('6301'),
        },
        {
            label: 'Selisih persediaan',
            amount: -from.stockDifference,
            term: 'stockDifference',
            href: ledger('6201'),
        },
        {
            label: loss ? 'Rugi bersih' : 'Laba bersih',
            amount: from.netProfit,
            term: 'netProfit',
            level: 'total',
            note: from === report && loss && (
                <LabelBadge icon={TrendingDown} label="Rugi" tone="danger" />
            ),
        },
    ];
    const rows = withPrevious(lines(report), lines(previous));
    const costs =
        report.expenses +
        report.otherExpenses +
        report.depreciation +
        report.stockDifference;
    const spent = report.cogs + costs;
    const income = report.sales + report.otherIncome;
    const keptPerHundred =
        income > 0 ? Math.round((report.netProfit / income) * 100) : null;
    const best = trend.reduce(
        (top, point) => (point.netProfit > top.netProfit ? point : top),
        trend[0],
    );

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
                    description={`Untung atau rugi usaha ${scope}, dari semua penjualan dan biaya.`}
                    actions={<ReportToolbar frame={props} />}
                />

                <ReportLead story={story} />

                <div className="grid grid-cols-1 gap-4 md:gap-6 lg:grid-cols-12">
                    <SectionCard
                        title="Laporan laba rugi"
                        description={
                            <span className="print:hidden">
                                Tekan nama akun untuk melihat catatannya.
                            </span>
                        }
                        className="lg:col-span-7"
                    >
                        {empty ? (
                            <EmptyState
                                icon={NotebookText}
                                title={`Belum ada penjualan atau biaya ${scope}`}
                                description="Laporan ini terisi sendiri dari catatan transaksi."
                                action={
                                    <Button asChild variant="outline">
                                        <Link href={create()}>
                                            Catat transaksi
                                        </Link>
                                    </Button>
                                }
                            />
                        ) : (
                            <Statement
                                rows={rows}
                                columns={{
                                    current: period.key
                                        ? period.label
                                        : 'Periode ini',
                                    previous: previousLabel,
                                }}
                            />
                        )}
                    </SectionCard>

                    {!empty && (
                        <SectionCard
                            title="Uang penjualan lari ke mana?"
                            className="lg:col-span-5 print:hidden"
                        >
                            <p className="mb-3 text-sm">
                                {keptPerHundred === null
                                    ? `Ada pengeluaran ${formatRupiah(spent)}, tapi belum ada penjualan.`
                                    : keptPerHundred >= 0
                                      ? `Dari tiap Rp100 penjualan, Rp${keptPerHundred} tersisa sebagai laba.`
                                      : `Tiap Rp100 penjualan, usaha rugi Rp${Math.abs(keptPerHundred)}.`}
                            </p>
                            <BreakdownBars
                                label="Pembagian uang penjualan"
                                rows={[
                                    {
                                        label: 'Modal barang (HPP)',
                                        amount: report.cogs,
                                        href: ledger('5101'),
                                    },
                                    {
                                        label: 'Biaya',
                                        amount: costs,
                                        href: ledger('6101'),
                                    },
                                    {
                                        label: loss
                                            ? 'Rugi bersih'
                                            : 'Sisa laba',
                                        amount: Math.abs(report.netProfit),
                                    },
                                ]}
                            />
                        </SectionCard>
                    )}

                    <SectionCard
                        title="Bagaimana laba enam bulan terakhir?"
                        className={cn(
                            'print:hidden',
                            empty ? 'lg:col-span-5' : 'lg:col-span-12',
                        )}
                    >
                        <p className="mb-2 text-sm">
                            Paling tinggi {best.label}, sebesar{' '}
                            <span className="whitespace-nowrap">
                                <Money
                                    value={best.netProfit}
                                    size="sm"
                                    className="font-semibold"
                                />
                                .
                            </span>
                        </p>
                        <MonthlyBars
                            caption="Laba bersih per bulan"
                            highlight={best.month}
                            data={trend.map((point) => ({
                                month: point.month,
                                label: point.label,
                                value: point.netProfit,
                            }))}
                        />
                    </SectionCard>
                </div>
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
