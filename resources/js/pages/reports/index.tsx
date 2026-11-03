import { Head, Link } from '@inertiajs/react';
import {
    BookOpen,
    CircleCheck,
    Package,
    Scale,
    TriangleAlert,
    Wallet,
    Landmark,
} from 'lucide-react';
import { SoonTag } from '@/components/app/action-button';
import { LabelBadge } from '@/components/app/badges';
import { MetricTile } from '@/components/app/metric';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { ReportToolbar } from '@/features/reports/report-toolbar';
import { show as onboarding } from '@/routes/onboarding';
import reports from '@/routes/reports';
import type { ReportsIndexProps } from '@/types/domain';

export default function ReportsIndex(props: ReportsIndexProps) {
    const { period, netProfit, closingEquity, balanceSheet, startedOn } = props;
    const query = { query: { periode: period.key } };
    const scope = period.label.toLowerCase();

    return (
        <>
            <Head title="Laporan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PageHeader
                    title="Laporan"
                    description="Keuangan usaha dari semua catatanmu. Pilih laporan untuk melihat rinciannya."
                    actions={
                        startedOn && (
                            <ReportToolbar frame={props} printable={false} />
                        )
                    }
                />

                {startedOn === null ? (
                    <EmptyState
                        variant="page"
                        icon={BookOpen}
                        title="Pembukuan belum dibuka"
                        description="Laporan butuh saldo awal: uang, peralatan, utang, dan piutang yang sudah ada saat mulai."
                        action={
                            <Button asChild>
                                <Link href={onboarding()}>
                                    Catat saldo awal
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <MetricTile
                            label="Laba rugi"
                            icon={Scale}
                            tone={netProfit < 0 ? 'danger' : 'neutral'}
                            value={<Money value={netProfit} size="xl" />}
                            detail={`${netProfit < 0 ? 'Rugi' : 'Laba'} bersih ${scope}`}
                            href={reports.profitLoss.url(query)}
                        />
                        <MetricTile
                            label="Perubahan modal"
                            icon={Wallet}
                            value={<Money value={closingEquity} size="xl" />}
                            detail={`Modal akhir ${scope}, dan kenapa laba tidak sama dengan kas`}
                            href={reports.equity.url(query)}
                        />
                        <MetricTile
                            label="Neraca"
                            icon={Landmark}
                            value={
                                <div className="flex flex-col gap-1.5">
                                    <Money
                                        value={balanceSheet.assets.total}
                                        size="xl"
                                    />
                                    {balanceSheet.balanced ? (
                                        <LabelBadge
                                            icon={CircleCheck}
                                            label="Seimbang"
                                            tone="success"
                                        />
                                    ) : (
                                        <LabelBadge
                                            icon={TriangleAlert}
                                            label="Tidak seimbang"
                                            tone="danger"
                                        />
                                    )}
                                </div>
                            }
                            detail="Total aset hari ini"
                            href={reports.balanceSheet.url()}
                        />
                        <MetricTile
                            label="Buku kas"
                            icon={BookOpen}
                            value={<SoonTag className="w-fit" />}
                            detail="Mutasi kas per rekening dengan saldo berjalan"
                        />
                        <MetricTile
                            label="Produk terlaris"
                            icon={Package}
                            value={<SoonTag className="w-fit" />}
                            detail="Produk paling laku dan paling untung"
                        />
                    </div>
                )}
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Laporan', href: reports.index() }],
};
