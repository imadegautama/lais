import { Head, Link } from '@inertiajs/react';
import {
    BookOpen,
    ChevronRight,
    Hammer,
    Landmark,
    ListTree,
    NotebookPen,
    NotebookText,
    Package,
    Scale,
    Sigma,
    Wallet,
    type LucideIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { DeltaBadge, MetricInline } from '@/components/app/metric';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { ReportLead } from '@/features/reports/report-lead';
import { ReportToolbar } from '@/features/reports/report-toolbar';
import { MonthlyBars } from '@/features/reports/trend-chart';
import { accountingPolicies } from '@/features/reports/vocabulary';
import { index as fixedAssets } from '@/routes/fixed-assets';
import { show as onboarding } from '@/routes/onboarding';
import reports from '@/routes/reports';
import type { ReportsIndexProps } from '@/types/domain';

function Group({ title, children }: { title: string; children: ReactNode }) {
    return (
        <SectionCard title={title}>
            <ul className="flex flex-col divide-y">{children}</ul>
        </SectionCard>
    );
}

/** One report: what it is, its key figure, and the way in. */
function ReportLink({
    icon: Icon,
    label,
    detail,
    value,
    href,
}: {
    icon: LucideIcon;
    label: string;
    detail: ReactNode;
    value?: ReactNode;
    href: string;
}) {
    return (
        <li>
            <Link
                href={href}
                className="-mx-2 flex min-h-11 items-center gap-3 rounded-lg px-2 py-2.5 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <Icon
                    className="size-4 shrink-0 text-muted-foreground"
                    aria-hidden
                />
                <span className="min-w-0 flex-1">
                    <span className="block text-sm font-medium">{label}</span>
                    <span className="block text-xs text-muted-foreground">
                        {detail}
                    </span>
                </span>
                {value}
                <ChevronRight
                    className="size-4 shrink-0 text-muted-foreground"
                    aria-hidden
                />
            </Link>
        </li>
    );
}

export default function ReportsIndex(props: ReportsIndexProps) {
    const { period, netProfit, previousNetProfit, closingEquity } = props;
    const { balanceSheet, startedOn, story, trend, query } = props;
    const link = { query };
    const scope = period.key ? period.label.toLowerCase() : period.label;
    const months = trend.filter((point) => point.sales !== 0);
    const average =
        months.length > 0
            ? months.reduce((sum, point) => sum + point.netProfit, 0) /
              months.length
            : 0;

    return (
        <>
            <Head title="Laporan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PageHeader
                    title="Laporan"
                    description="Keuangan usaha dari semua catatanmu. Pilih laporan untuk melihat rinciannya."
                    actions={<ReportToolbar frame={props} printable={false} />}
                />

                {startedOn === null ? (
                    <EmptyState
                        variant="page"
                        icon={Scale}
                        title="Pembukuan belum dibuka"
                        description="Isi saldo awal dulu, nanti semua laporan terisi sendiri dari catatanmu."
                        action={
                            <Button asChild>
                                <Link href={onboarding()}>Buka pembukuan</Link>
                            </Button>
                        }
                    />
                ) : (
                    <>
                        <ReportLead story={story} />

                        <div className="grid grid-cols-1 gap-4 md:gap-6 lg:grid-cols-12">
                            <SectionCard
                                title="Apakah usaha makin untung?"
                                className="lg:col-span-8"
                            >
                                <p className="mb-2 text-sm">
                                    Rata-rata laba{' '}
                                    <Money
                                        value={Math.round(average)}
                                        size="sm"
                                        className="font-semibold"
                                    />{' '}
                                    per bulan dalam enam bulan terakhir.
                                </p>
                                <MonthlyBars
                                    caption="Laba bersih per bulan"
                                    data={trend.map((point) => ({
                                        month: point.month,
                                        label: point.label,
                                        value: point.netProfit,
                                    }))}
                                />
                            </SectionCard>

                            <SectionCard
                                title={`Angka ${scope}`}
                                className="lg:col-span-4"
                            >
                                <div className="flex flex-col divide-y">
                                    <MetricInline
                                        label="Penjualan"
                                        value={<Money value={props.sales} />}
                                    />
                                    <MetricInline
                                        label="Laba bersih"
                                        value={<Money value={netProfit} />}
                                        delta={
                                            <DeltaBadge
                                                value={netProfit}
                                                previous={previousNetProfit}
                                                comparisonLabel={`vs ${props.previousLabel.toLowerCase()}`}
                                            />
                                        }
                                    />
                                    <MetricInline
                                        label="Uang usaha sekarang"
                                        value={<Money value={props.money} />}
                                    />
                                </div>
                            </SectionCard>
                        </div>

                        <div className="grid grid-cols-1 items-start gap-4 md:gap-6 xl:grid-cols-3">
                            <Group title="Kesehatan usaha">
                                <ReportLink
                                    icon={Scale}
                                    label="Laba rugi"
                                    detail={`${netProfit < 0 ? 'Rugi' : 'Laba'} bersih ${scope}`}
                                    value={
                                        <Money value={netProfit} size="sm" />
                                    }
                                    href={reports.profitLoss.url(link)}
                                />
                                <ReportLink
                                    icon={Landmark}
                                    label="Posisi keuangan"
                                    detail={
                                        balanceSheet.balanced
                                            ? 'Total aset hari ini, seimbang'
                                            : 'Total aset hari ini, belum seimbang'
                                    }
                                    value={
                                        <Money
                                            value={balanceSheet.assets.total}
                                            size="sm"
                                        />
                                    }
                                    href={reports.balanceSheet.url()}
                                />
                                <ReportLink
                                    icon={Wallet}
                                    label="Perubahan modal"
                                    detail="Modal akhir, dan kenapa laba tidak sama dengan kas"
                                    value={
                                        <Money
                                            value={closingEquity}
                                            size="sm"
                                        />
                                    }
                                    href={reports.equity.url(link)}
                                />
                            </Group>

                            <Group title="Uang dan produk">
                                <ReportLink
                                    icon={BookOpen}
                                    label="Buku kas"
                                    detail={`Perubahan uang ${scope}`}
                                    value={
                                        <Money
                                            value={props.cashChange}
                                            size="sm"
                                        />
                                    }
                                    href={reports.cashBook.url(link)}
                                />
                                <ReportLink
                                    icon={Package}
                                    label="Produk terlaris"
                                    detail={
                                        props.topProduct
                                            ? `${props.topProduct} paling untung ${scope}`
                                            : `Belum ada penjualan ${scope}`
                                    }
                                    href={reports.products.url(link)}
                                />
                                <ReportLink
                                    icon={Hammer}
                                    label="Aset tetap"
                                    detail={
                                        props.unsetAssets > 0
                                            ? `${props.unsetAssets} peralatan belum punya umur pakai`
                                            : 'Nilai buku peralatan'
                                    }
                                    value={
                                        <Money
                                            value={props.equipmentValue}
                                            size="sm"
                                        />
                                    }
                                    href={fixedAssets.url()}
                                />
                            </Group>

                            <Group title="Pembukuan">
                                <ReportLink
                                    icon={ListTree}
                                    label="Daftar akun (COA)"
                                    detail={`${props.accountCount} akun, dengan saldo dan buku besarnya`}
                                    href={reports.chart.url()}
                                />
                                <ReportLink
                                    icon={NotebookText}
                                    label="Jurnal umum"
                                    detail={`Semua pencatatan ${scope}, urut tanggal`}
                                    href={reports.journal.url(link)}
                                />
                                <ReportLink
                                    icon={Sigma}
                                    label="Neraca saldo"
                                    detail="Bukti semua akun seimbang"
                                    value={
                                        <Money
                                            value={props.balancedTotal}
                                            size="sm"
                                        />
                                    }
                                    href={reports.trialBalance.url()}
                                />
                                <ReportLink
                                    icon={NotebookPen}
                                    label="Catatan laporan"
                                    detail={`Dasar penyusunan, ${accountingPolicies.length} kebijakan, dan rincian akun`}
                                    href={reports.notes.url(link)}
                                />
                            </Group>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Laporan', href: reports.index() }],
};
