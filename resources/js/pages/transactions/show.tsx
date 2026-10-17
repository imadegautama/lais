import { Head, Link } from '@inertiajs/react';
import {
    Ban,
    History,
    PackageOpen,
    Pencil,
    ReceiptText,
    ScrollText,
} from 'lucide-react';
import { ActivityList } from '@/components/app/activity-list';
import {
    CategoryBadge,
    methodLabel,
    SourceLabel,
    VoidBadge,
} from '@/components/app/badges';
import { DetailRow } from '@/components/app/detail-row';
import { MetricInline } from '@/components/app/metric';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { StockMovementList } from '@/components/app/stock-movement-list';
import { Button } from '@/components/ui/button';
import { CancelDialog } from '@/features/transactions/cancel-dialog';
import { JournalCard } from '@/features/transactions/journal-card';
import { contactTypeFor } from '@/features/transactions/record';
import { formatDate, formatQty, formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';
import { edit, index, show } from '@/routes/transactions';
import type { TransactionDetailProps } from '@/types/domain';
import {
    contactTypeLabels,
    PaymentMethod,
    TransactionType,
    transactionKindLabels,
} from '@/types/enums';

export default function TransactionShow({
    transaction,
    lines,
    movements,
    journal,
    activities,
}: TransactionDetailProps) {
    const hpp = lines.reduce((sum, line) => sum + (line.hpp ?? 0), 0);
    const isSale = transaction.type === TransactionType.Penjualan;
    const contactType = contactTypeFor(transaction.type, transaction.kind);

    return (
        <>
            <Head title={transaction.description} />
            <div className="flex flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PageHeader
                    title={transaction.description}
                    meta={
                        transaction.voided ? (
                            <VoidBadge />
                        ) : (
                            <CategoryBadge type={transaction.type} />
                        )
                    }
                    description={`${formatDate(transaction.occurredAt, 'long')}, ${formatDate(transaction.occurredAt, 'time')} WIB`}
                    actions={
                        !transaction.voided && (
                            <>
                                <Button variant="outline" asChild>
                                    <Link href={edit(transaction.id)}>
                                        <Pencil aria-hidden />
                                        Ubah
                                    </Link>
                                </Button>
                                <CancelDialog id={transaction.id} />
                            </>
                        )
                    }
                />

                {transaction.replacesId && (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <History className="size-4 shrink-0" aria-hidden />
                        <span>
                            Catatan ini mengganti versi sebelumnya.{' '}
                            <Link
                                href={show(transaction.replacesId)}
                                className="font-medium text-foreground underline underline-offset-4"
                            >
                                Lihat versi lama
                            </Link>
                        </span>
                    </p>
                )}

                {transaction.voided && (
                    <div
                        role="status"
                        className="flex gap-3 rounded-lg bg-destructive-soft px-4 py-3 text-sm"
                    >
                        <Ban
                            className="mt-0.5 size-4 shrink-0 text-destructive"
                            aria-hidden
                        />
                        <p>
                            <span className="font-semibold text-destructive">
                                {transaction.replacedById
                                    ? 'Diubah'
                                    : 'Dibatalkan'}
                                {transaction.voidedAt &&
                                    ` ${formatDate(transaction.voidedAt, 'datetime')}`}
                                .
                            </span>{' '}
                            {transaction.voidReason &&
                                `Alasan: ${transaction.voidReason}. `}
                            Stok dan pembukuannya sudah dikembalikan.
                            {transaction.replacedById && (
                                <>
                                    {' '}
                                    <Link
                                        href={show(transaction.replacedById)}
                                        className="font-medium underline underline-offset-4"
                                    >
                                        Lihat versi baru
                                    </Link>
                                </>
                            )}
                        </p>
                    </div>
                )}

                <div className="grid grid-cols-1 gap-4 md:gap-6 lg:grid-cols-12">
                    <div className="flex min-w-0 flex-col gap-4 md:gap-6 lg:col-span-7">
                        <SectionCard title="Rincian" icon={ReceiptText}>
                            {lines.length > 0 ? (
                                <ul className="flex flex-col divide-y">
                                    {lines.map((line) => (
                                        <li
                                            key={line.id}
                                            className="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0"
                                        >
                                            <div className="flex min-w-0 flex-col gap-0.5">
                                                <span className="font-medium">
                                                    {line.name}
                                                </span>
                                                <span className="num text-xs text-muted-foreground">
                                                    {formatQty(
                                                        line.qty,
                                                        line.unit,
                                                    )}{' '}
                                                    ×{' '}
                                                    <Money
                                                        value={line.price}
                                                        size="sm"
                                                        className="text-xs font-normal"
                                                    />
                                                    {line.hpp !== null && (
                                                        <>
                                                            {' '}
                                                            · HPP{' '}
                                                            <Money
                                                                value={line.hpp}
                                                                size="sm"
                                                                className="text-xs font-normal"
                                                            />
                                                        </>
                                                    )}
                                                </span>
                                            </div>
                                            <Money
                                                value={line.subtotal}
                                                size="sm"
                                            />
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <dl className="flex flex-col divide-y text-sm">
                                    {transaction.kind && (
                                        <DetailRow label="Jenis">
                                            {
                                                transactionKindLabels[
                                                    transaction.kind
                                                ]
                                            }
                                        </DetailRow>
                                    )}
                                    <DetailRow label="Nominal">
                                        <Money value={transaction.amount} />
                                    </DetailRow>
                                    {transaction.note && (
                                        <DetailRow label="Catatan">
                                            {transaction.note}
                                        </DetailRow>
                                    )}
                                </dl>
                            )}
                        </SectionCard>

                        {movements.length > 0 && (
                            <SectionCard
                                title="Perubahan stok"
                                icon={PackageOpen}
                                description="Stok yang bergerak karena catatan ini."
                            >
                                <StockMovementList
                                    movements={movements}
                                    by="item"
                                />
                            </SectionCard>
                        )}

                        <JournalCard journal={journal} />

                        <SectionCard
                            title="Riwayat"
                            icon={ScrollText}
                            description="Siapa mencatat, mengubah, atau membatalkan catatan ini, dan lewat mana."
                        >
                            <ActivityList entries={activities} />
                        </SectionCard>
                    </div>

                    {/* On phones the totals come first; on desktop they sit on the right. */}
                    <div className="order-first flex min-w-0 flex-col gap-4 md:gap-6 lg:order-none lg:col-span-5">
                        <SectionCard
                            title="Ringkasan"
                            tone="focus"
                            className="lg:sticky lg:top-18"
                        >
                            <div className="flex flex-col divide-y">
                                <MetricInline
                                    label="Total"
                                    value={
                                        <Money
                                            value={transaction.amount}
                                            size="lg"
                                            className={cn(
                                                transaction.voided &&
                                                    'text-muted-foreground line-through',
                                            )}
                                        />
                                    }
                                />
                                {isSale && (
                                    <>
                                        <MetricInline
                                            label="HPP"
                                            value={<Money value={hpp} />}
                                        />
                                        <MetricInline
                                            label="Laba kotor"
                                            value={
                                                <Money
                                                    value={
                                                        transaction.amount - hpp
                                                    }
                                                />
                                            }
                                        />
                                    </>
                                )}
                                <MetricInline
                                    label="Cara bayar"
                                    value={
                                        <span className="text-sm font-medium">
                                            {methodLabel(
                                                transaction.type,
                                                transaction.method,
                                            )}
                                        </span>
                                    }
                                    detail={
                                        isSale &&
                                        transaction.method ===
                                            PaymentMethod.Kredit
                                            ? `Dibayar di tempat ${formatRupiah(transaction.paidAmount)}`
                                            : undefined
                                    }
                                />
                                {transaction.contact && (
                                    <MetricInline
                                        label={contactTypeLabels[contactType]}
                                        value={
                                            <span className="text-sm font-medium">
                                                {transaction.contact}
                                            </span>
                                        }
                                    />
                                )}
                                <MetricInline
                                    label="Kas usaha"
                                    value={
                                        <Money
                                            value={transaction.cashFlow}
                                            direction={
                                                transaction.cashFlow > 0
                                                    ? 'in'
                                                    : transaction.cashFlow < 0
                                                      ? 'out'
                                                      : 'none'
                                            }
                                        />
                                    }
                                    detail={
                                        transaction.cashFlow === 0
                                            ? 'Tidak ada uang usaha yang bergerak.'
                                            : undefined
                                    }
                                />
                                <MetricInline
                                    label="Sumber"
                                    value={
                                        <SourceLabel
                                            source={transaction.source}
                                        />
                                    }
                                />
                            </div>
                        </SectionCard>
                    </div>
                </div>
            </div>
        </>
    );
}

TransactionShow.layout = {
    breadcrumbs: [{ title: 'Transaksi', href: index() }],
};
