import { Head, router } from '@inertiajs/react';
import {
    ChartColumn,
    CircleCheck,
    ClipboardList,
    HandCoins,
    HeartPulse,
    House,
    Landmark,
    ListChecks,
    NotebookPen,
    PackageMinus,
    ReceiptText,
    Scale,
    Flag,
    TriangleAlert,
    Wallet,
    type LucideIcon,
} from 'lucide-react';
import { useState } from 'react';
import { ActionButton, SoonTag } from '@/components/app/action-button';
import { DeltaBadge, MetricTile } from '@/components/app/metric';
import { Money } from '@/components/app/money';
import { OnboardingReminder } from '@/components/app/onboarding-reminder';
import { PageHeader } from '@/components/app/page-header';
import { PeriodPicker } from '@/components/app/period-picker';
import { SalesBarChart } from '@/components/app/sales-bar-chart';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState, ErrorState } from '@/components/app/states';
import { TransactionTable } from '@/features/transactions/transaction-table';
import { Progress } from '@/components/ui/progress';
import {
    formatDate,
    formatPercent,
    formatQty,
    formatRupiah,
    formatRupiahCompact,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { create as createItem } from '@/routes/items';
import { index as moneyAccountsIndex } from '@/routes/money-accounts';
import {
    create as createTransaction,
    index as transactionsIndex,
} from '@/routes/transactions';
import type { ActionItem, DashboardProps, PeriodKey } from '@/types/domain';
import { TransactionType } from '@/types/enums';

function greeting(): string {
    const hour = Number(
        new Intl.DateTimeFormat('id-ID', {
            hour: 'numeric',
            hourCycle: 'h23',
            timeZone: 'Asia/Jakarta',
        }).format(new Date()),
    );

    if (hour < 11) return 'Selamat pagi';
    if (hour < 15) return 'Selamat siang';
    if (hour < 18) return 'Selamat sore';

    return 'Selamat malam';
}

const actionIcons: Record<ActionItem['kind'], LucideIcon> = {
    pesanan: ClipboardList,
    stok: PackageMinus,
    piutang: NotebookPen,
};

export default function Dashboard(props: DashboardProps) {
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState<PeriodKey | null>(null);

    const changePeriod = (key: PeriodKey) => {
        router.get(
            dashboard.url(),
            { periode: key },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => {
                    setFailed(null);
                    setLoading(true);
                },
                onHttpException: () => {
                    setFailed(key);

                    return false;
                },
                onNetworkError: () => {
                    setFailed(key);

                    return false;
                },
                onFinish: () => setLoading(false),
            },
        );
    };

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PageHeader
                    title={`${greeting()}, ${props.business.ownerName}`}
                    description={`${props.business.name} · ${formatDate(new Date(), 'long')}`}
                    actions={
                        !props.isEmpty && (
                            <PeriodPicker
                                periods={props.periods}
                                value={props.period.key}
                                onChange={changePeriod}
                                disabled={loading}
                            />
                        )
                    }
                />

                {props.needsOnboarding && <OnboardingReminder />}

                {failed && (
                    <ErrorState
                        title="Ringkasan periode ini gagal dimuat"
                        description="Koneksi ke server terputus. Angka di bawah masih dari periode sebelumnya."
                        onRetry={() => changePeriod(failed)}
                    />
                )}

                {props.isEmpty ? (
                    <FirstSteps />
                ) : (
                    <Overview {...props} loading={loading} />
                )}
            </div>
        </>
    );
}

function Overview(props: DashboardProps & { loading: boolean }) {
    const { wallets } = props;
    const drawnShare =
        wallets.netProfitThisMonth > 0
            ? (wallets.drawnThisMonth / wallets.netProfitThisMonth) * 100
            : null;

    return (
        <>
            <SummaryCards {...props} />

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-6 xl:grid-cols-12">
                <ActionsPanel
                    actions={props.actions}
                    className="md:col-span-2 xl:col-span-7"
                />

                <HealthCard
                    rules={props.health}
                    className="md:col-span-2 xl:col-span-5"
                />

                <SectionCard
                    title="Hari apa paling ramai?"
                    description="Penjualan per hari, 30 hari terakhir"
                    icon={ChartColumn}
                    className="md:col-span-2 xl:col-span-8"
                >
                    {props.salesChart.some((point) => point.sales > 0) ? (
                        <SalesBarChart data={props.salesChart} />
                    ) : (
                        <EmptyState
                            title="Belum ada penjualan 30 hari terakhir"
                            description="Grafik muncul setelah penjualan pertama dicatat."
                        />
                    )}
                </SectionCard>

                <SectionCard
                    title="Uang usaha dan uang pribadi"
                    description="Dipisah supaya laba tidak tercampur kebutuhan rumah."
                    icon={Wallet}
                    className="md:self-start xl:col-span-4 xl:self-stretch"
                >
                    <div className="flex flex-col gap-4">
                        <div className="grid grid-cols-2 overflow-hidden rounded-lg border">
                            <div className="flex flex-col gap-1 p-3">
                                <span className="text-xs font-medium text-muted-foreground">
                                    Uang usaha
                                </span>
                                <Money value={wallets.businessCash} size="lg" />
                                <span className="text-xs text-muted-foreground">
                                    Kas tunai + non-tunai
                                </span>
                            </div>
                            <div className="flex flex-col gap-1 border-l bg-secondary p-3">
                                <span className="text-xs font-medium text-muted-foreground">
                                    Diambil untuk rumah
                                </span>
                                <Money
                                    value={wallets.drawnThisMonth}
                                    size="lg"
                                />
                                <span className="text-xs text-muted-foreground">
                                    Bulan ini
                                </span>
                            </div>
                        </div>
                        {drawnShare !== null && (
                            <div className="flex flex-col gap-1.5">
                                <Progress
                                    value={Math.min(100, drawnShare)}
                                    aria-label="Bagian laba bulan ini yang diambil untuk pribadi"
                                />
                                <p className="text-sm">
                                    Diambil{' '}
                                    <strong className="num">
                                        {formatPercent(drawnShare, 0)}
                                    </strong>{' '}
                                    dari laba bulan ini.
                                    {drawnShare > 100 &&
                                        ' Modal usaha ikut terpakai.'}
                                </p>
                            </div>
                        )}
                        <p className="text-sm text-muted-foreground">
                            Modal usaha{' '}
                            <span className="num font-semibold text-foreground">
                                {formatRupiah(wallets.equityOpening)}
                            </span>{' '}
                            di awal bulan, sekarang{' '}
                            <span className="num font-semibold text-foreground">
                                {formatRupiah(wallets.equityNow)}
                            </span>
                            .
                        </p>
                    </div>
                </SectionCard>

                {/* Beside the wallets card on tablets; right of the table on xl. */}
                <StockCard
                    alerts={props.stockAlerts}
                    className="md:self-start xl:col-span-4 xl:col-start-9 xl:row-start-3"
                />

                <SectionCard
                    title="Transaksi terakhir"
                    description="Catatan terbaru dari WhatsApp, web, dan halaman toko"
                    icon={ReceiptText}
                    className="md:col-span-2 xl:col-span-8 xl:col-start-1 xl:row-start-3"
                >
                    <TransactionTable rows={props.recentTransactions} />
                </SectionCard>
            </div>
        </>
    );
}

/**
 * The period at a glance (UI-PAGES C1): four tiles, each a number, its
 * change against the named period before, and one line saying what it
 * means. Tiles open the records behind them where a page exists.
 */
function SummaryCards({
    summary,
    period,
    loading,
}: DashboardProps & { loading: boolean }) {
    const span = period.label.toLowerCase();
    const delta = (
        value: number,
        previous: number | null,
        goodWhen?: 'up' | 'neutral',
    ) => (
        <DeltaBadge
            value={value}
            previous={previous}
            comparisonLabel={period.comparisonLabel}
            goodWhen={goodWhen}
        />
    );
    // Big on wide screens, still whole in a two-column tile at 360 px.
    const amount = (value: number) => (
        <Money
            value={value}
            size="xl"
            className="text-xl md:text-[28px] xl:text-[32px]"
        />
    );

    return (
        <section
            aria-label={`Ringkasan ${span}`}
            aria-busy={loading || undefined}
            className={cn(
                'grid grid-cols-2 gap-3 transition-opacity md:gap-4 lg:grid-cols-4',
                loading && 'opacity-60',
            )}
        >
            <MetricTile
                label="Penjualan"
                icon={HandCoins}
                href={transactionsIndex.url({
                    query: {
                        jenis: TransactionType.Penjualan,
                        periode: period.key,
                    },
                })}
                value={
                    <div className="flex flex-col gap-1.5">
                        {amount(summary.sales.value)}
                        {delta(summary.sales.value, summary.sales.previous)}
                    </div>
                }
                detail={`${summary.sales.count} transaksi ${span}`}
            />
            <MetricTile
                label="Untung bersih"
                icon={Scale}
                tone={summary.netProfit.value < 0 ? 'danger' : 'neutral'}
                value={
                    <div className="flex flex-col gap-1.5">
                        {amount(summary.netProfit.value)}
                        {delta(
                            summary.netProfit.value,
                            summary.netProfit.previous,
                        )}
                    </div>
                }
                detail="Penjualan dikurangi modal bahan dan biaya"
            />
            <MetricTile
                label="Uang usaha"
                icon={Landmark}
                href={moneyAccountsIndex.url()}
                value={amount(summary.cash.total)}
                detail={`Saat ini · tunai ${formatRupiahCompact(summary.cash.onHand)} · non-tunai ${formatRupiahCompact(summary.cash.nonCash)}`}
            />
            <MetricTile
                label="Diambil untuk pribadi"
                icon={House}
                href={transactionsIndex.url({
                    query: {
                        jenis: TransactionType.Modal,
                        periode: period.key,
                    },
                })}
                value={
                    <div className="flex flex-col gap-1.5">
                        {amount(summary.personalDraw.value)}
                        {delta(
                            summary.personalDraw.value,
                            summary.personalDraw.previous,
                            'neutral',
                        )}
                    </div>
                }
                detail={`Untuk keperluan rumah, ${span}`}
            />
            {loading && (
                <span role="status" className="sr-only">
                    Memuat ringkasan {span}
                </span>
            )}
        </section>
    );
}

function ActionsPanel({
    actions,
    className,
}: {
    actions: ActionItem[];
    className?: string;
}) {
    return (
        <SectionCard
            tone="focus"
            title="Perlu tindakan hari ini"
            description={
                actions.length > 0
                    ? `${actions.length} hal menunggu keputusan Anda`
                    : 'Semua beres untuk saat ini'
            }
            icon={ListChecks}
            className={className}
            contentClassName="pt-0 md:pt-0"
        >
            {actions.length === 0 ? (
                <EmptyState
                    className="mt-4"
                    icon={CircleCheck}
                    title="Tidak ada yang perlu dicek"
                    description="Pesanan baru, bahan yang hampir habis, dan tagihan yang lewat 7 hari akan muncul di sini."
                />
            ) : (
                <ul className="divide-y">
                    {actions.map((action) => {
                        const Icon = actionIcons[action.kind];

                        return (
                            <li
                                key={action.id}
                                className="flex flex-col gap-3 py-3.5 sm:flex-row sm:items-center"
                            >
                                <div className="flex min-w-0 flex-1 gap-3">
                                    <Icon
                                        className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                        aria-hidden
                                    />
                                    <div className="min-w-0">
                                        <p className="text-sm font-semibold">
                                            {action.title}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {action.detail}
                                        </p>
                                    </div>
                                </div>
                                <ActionButton
                                    target={action.target}
                                    variant="outline"
                                    size="sm"
                                    className="self-start sm:self-center"
                                >
                                    {action.actionLabel}
                                </ActionButton>
                            </li>
                        );
                    })}
                </ul>
            )}
        </SectionCard>
    );
}

function HealthCard({
    rules,
    className,
}: {
    rules: DashboardProps['health'];
    className?: string;
}) {
    const failing = rules.filter((rule) => !rule.passed).length;

    return (
        <SectionCard
            className={className}
            title="Kesehatan usaha"
            description={
                failing === 0
                    ? 'Keempat pemeriksaan aman'
                    : `${failing} dari 4 pemeriksaan perlu perhatian`
            }
            icon={HeartPulse}
        >
            <ul className="flex flex-col gap-3">
                {rules.map((rule) => (
                    <li key={rule.key} className="flex gap-2.5">
                        {rule.passed ? (
                            <CircleCheck
                                className="mt-0.5 size-4 shrink-0 text-success"
                                aria-hidden
                            />
                        ) : (
                            <TriangleAlert
                                className="mt-0.5 size-4 shrink-0 text-warning"
                                aria-hidden
                            />
                        )}
                        <div className="min-w-0">
                            <p className="text-sm font-medium">
                                {rule.label}
                                <span
                                    className={cn(
                                        'ml-1.5 text-xs font-semibold',
                                        rule.passed
                                            ? 'text-success'
                                            : 'text-warning',
                                    )}
                                >
                                    {rule.passed ? 'Aman' : 'Perlu perhatian'}
                                </span>
                            </p>
                            <p className="num text-xs text-muted-foreground">
                                {rule.detail}
                            </p>
                        </div>
                    </li>
                ))}
            </ul>
            <p className="mt-4 text-xs text-muted-foreground">
                Aturan dari buku{' '}
                <cite>Akuntansi UMKM: Panduan Praktis untuk Pemula</cite>{' '}
                (Alliyah dkk., 2025).
            </p>
        </SectionCard>
    );
}

function StockCard({
    alerts,
    className,
}: {
    alerts: DashboardProps['stockAlerts'];
    className?: string;
}) {
    return (
        <SectionCard
            className={className}
            title="Bahan hampir habis"
            description="Perkiraan dari pemakaian 7 hari terakhir"
            icon={PackageMinus}
        >
            {alerts.length === 0 ? (
                <EmptyState
                    title="Stok aman"
                    description="Bahan yang tinggal cukup untuk 3 hari atau kurang akan muncul di sini."
                />
            ) : (
                <ul className="divide-y">
                    {alerts.map((alert) => (
                        <li
                            key={alert.id}
                            className="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                        >
                            <div className="min-w-0">
                                <p className="text-sm font-medium">
                                    {alert.name}
                                </p>
                                <p className="num text-xs text-muted-foreground">
                                    Sisa {formatQty(alert.stock, alert.unit)}
                                    {alert.minimum !== null &&
                                        ` · minimum ${formatQty(alert.minimum, alert.unit)}`}
                                </p>
                            </div>
                            <span
                                className={cn(
                                    'num shrink-0 rounded-md px-1.5 py-0.5 text-xs font-semibold',
                                    alert.daysLeft !== null &&
                                        alert.daysLeft <= 2
                                        ? 'bg-warning-soft text-warning'
                                        : 'bg-secondary text-secondary-foreground',
                                )}
                            >
                                {alert.daysLeft === null
                                    ? 'Data belum cukup'
                                    : `±${new Intl.NumberFormat('id-ID').format(alert.daysLeft)} hari`}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </SectionCard>
    );
}

// TODO(GOWA/A2): the WhatsApp and store steps link once built.
function FirstSteps() {
    const steps: Array<{
        title: string;
        detail: string;
        action?: { label: string; href: string };
    }> = [
        {
            title: 'Tambahkan produk dan resep',
            detail: 'Supaya stok bahan berkurang otomatis saat terjual.',
            action: { label: 'Tambah produk', href: createItem.url() },
        },
        {
            title: 'Catat penjualan pertama',
            detail: 'Dari tombol Catat, atau kirim pesan ke bot WhatsApp.',
            action: { label: 'Catat penjualan', href: createTransaction.url() },
        },
        {
            title: 'Hubungkan WhatsApp',
            detail: 'Ringkasan harian dan peringatan stok dikirim ke sana.',
        },
        {
            title: 'Bagikan link toko',
            detail: 'Pelanggan bisa pesan sendiri tanpa chat bolak-balik.',
        },
    ];

    return (
        <SectionCard
            tone="focus"
            title="Langkah pertama"
            description="Dashboard terisi setelah transaksi pertama tercatat."
            icon={Flag}
            contentClassName="pt-0 md:pt-0"
        >
            <ol className="divide-y">
                {steps.map((step, index) => (
                    <li
                        key={step.title}
                        className="flex items-start gap-3 py-3.5 sm:items-center"
                    >
                        <span className="num flex size-7 shrink-0 items-center justify-center rounded-full border text-sm font-semibold text-muted-foreground">
                            {index + 1}
                        </span>
                        {/* The button drops below the text on phones, as in the actions panel. */}
                        <div className="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-semibold">
                                    {step.title}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {step.detail}
                                </p>
                            </div>
                            {step.action ? (
                                <ActionButton
                                    target={{ href: step.action.href }}
                                    variant="outline"
                                    size="sm"
                                    className="self-start sm:self-center"
                                >
                                    {step.action.label}
                                </ActionButton>
                            ) : (
                                <SoonTag className="self-start sm:self-center" />
                            )}
                        </div>
                    </li>
                ))}
            </ol>
        </SectionCard>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
