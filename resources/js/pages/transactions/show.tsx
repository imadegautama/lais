import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import {
    Ban,
    BookOpenCheck,
    ChevronDown,
    PackageOpen,
    ReceiptText,
} from 'lucide-react';
import {
    CategoryBadge,
    methodLabel,
    SourceLabel,
    transactionKinds,
    VoidBadge,
} from '@/components/app/badges';
import { MetricInline } from '@/components/app/metric';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { formatDate, formatQty } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index } from '@/routes/transactions';
import type {
    StockMovementReason,
    TransactionDetailProps,
} from '@/types/domain';

const movementReasons: Record<StockMovementReason, string> = {
    stok_awal: 'Stok awal',
    koreksi: 'Koreksi stok',
    penjualan: 'Terjual',
    pembelian: 'Masuk dari belanja',
    pembatalan: 'Dikembalikan karena dibatalkan',
};

export default function TransactionShow({
    transaction,
    lines,
    movements,
    journal,
}: TransactionDetailProps) {
    const hpp = lines.reduce((sum, line) => sum + (line.hpp ?? 0), 0);
    const isSale = transaction.type === 'penjualan';

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
                />

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
                                Dibatalkan
                                {transaction.voidedAt &&
                                    ` ${formatDate(transaction.voidedAt, 'datetime')}`}
                                .
                            </span>{' '}
                            {transaction.voidReason &&
                                `Alasan: ${transaction.voidReason}. `}
                            Stok dan pembukuannya sudah dikembalikan.
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
                                        <Row label="Jenis">
                                            {transactionKinds[transaction.kind]}
                                        </Row>
                                    )}
                                    <Row label="Nominal">
                                        <Money value={transaction.amount} />
                                    </Row>
                                    {transaction.note && (
                                        <Row label="Catatan">
                                            {transaction.note}
                                        </Row>
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
                                <ul className="flex flex-col divide-y">
                                    {movements.map((movement) => (
                                        <li
                                            key={movement.id}
                                            className="flex items-baseline justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                                        >
                                            <div className="flex min-w-0 flex-col">
                                                <span className="text-sm font-medium">
                                                    {movement.name}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {
                                                        movementReasons[
                                                            movement.reason
                                                        ]
                                                    }
                                                    {movement.stockAfter !==
                                                        null &&
                                                        ` · sisa ${formatQty(movement.stockAfter, movement.unit)}`}
                                                </span>
                                            </div>
                                            <div className="flex flex-col items-end">
                                                <span
                                                    className={cn(
                                                        'num text-sm font-semibold',
                                                        movement.stockAfter !==
                                                            null &&
                                                            movement.stockAfter <
                                                                0 &&
                                                            'text-destructive',
                                                    )}
                                                >
                                                    {movement.qty > 0
                                                        ? '+'
                                                        : '−'}
                                                    {formatQty(
                                                        Math.abs(movement.qty),
                                                        movement.unit,
                                                    )}
                                                </span>
                                                <Money
                                                    value={movement.value}
                                                    size="sm"
                                                    className="text-xs font-normal text-muted-foreground"
                                                />
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </SectionCard>
                        )}

                        <JournalCard journal={journal} />
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
                                        transaction.method === 'kredit'
                                            ? `Dibayar di tempat Rp${new Intl.NumberFormat('id-ID').format(transaction.paidAmount)}`
                                            : undefined
                                    }
                                />
                                {transaction.contact && (
                                    <MetricInline
                                        label={
                                            isSale ||
                                            transaction.kind ===
                                                'terima_piutang'
                                                ? 'Pelanggan'
                                                : 'Supplier'
                                        }
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

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex items-baseline justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right font-medium">{children}</dd>
        </div>
    );
}

/** The accounting behind the transaction, closed by default (UI-PAGES C4). */
function JournalCard({
    journal,
}: {
    journal: TransactionDetailProps['journal'];
}) {
    return (
        <Collapsible className="rounded-xl border bg-card shadow-card">
            <CollapsibleTrigger asChild>
                <Button
                    variant="ghost"
                    className="group h-auto w-full justify-between rounded-xl px-4 py-4 text-left md:px-5"
                >
                    <span className="flex items-center gap-3">
                        <BookOpenCheck
                            className="size-[18px] text-muted-foreground"
                            aria-hidden
                        />
                        <span className="flex flex-col">
                            <span className="text-base font-semibold">
                                Lihat jurnal
                            </span>
                            <span className="text-sm font-normal text-muted-foreground">
                                Catatan akuntansi di balik transaksi ini.
                            </span>
                        </span>
                    </span>
                    <ChevronDown
                        className="size-4 transition-transform group-data-[state=open]:rotate-180"
                        aria-hidden
                    />
                </Button>
            </CollapsibleTrigger>
            <CollapsibleContent className="flex flex-col gap-4 px-4 pb-4 md:px-5 md:pb-5">
                <p className="text-sm text-muted-foreground">
                    Setiap transaksi dicatat di dua sisi. Debit dan kredit
                    selalu sama besar, jadi pembukuan tetap seimbang.
                </p>
                {journal.map((entry) => {
                    const debit = entry.lines.reduce((s, l) => s + l.debit, 0);
                    const credit = entry.lines.reduce(
                        (s, l) => s + l.credit,
                        0,
                    );

                    return (
                        <table key={entry.id} className="w-full text-sm">
                            <caption className="sr-only">
                                Jurnal {entry.description}
                            </caption>
                            <thead>
                                <tr className="border-b text-xs text-muted-foreground">
                                    <th className="py-2 text-left font-semibold">
                                        Akun
                                    </th>
                                    <th className="py-2 text-right font-semibold">
                                        Debit
                                    </th>
                                    <th className="py-2 text-right font-semibold">
                                        Kredit
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {entry.lines.map((line, i) => (
                                    <tr key={i} className="border-b">
                                        <td className="py-2">
                                            <span className="num text-muted-foreground">
                                                {line.code}
                                            </span>{' '}
                                            {line.account}
                                        </td>
                                        <td className="num py-2 text-right">
                                            {line.debit > 0 && (
                                                <Money
                                                    value={line.debit}
                                                    size="sm"
                                                />
                                            )}
                                        </td>
                                        <td className="num py-2 text-right">
                                            {line.credit > 0 && (
                                                <Money
                                                    value={line.credit}
                                                    size="sm"
                                                />
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="font-semibold">
                                    <td className="py-2">
                                        {debit === credit
                                            ? 'Seimbang'
                                            : 'Tidak seimbang'}
                                    </td>
                                    <td className="py-2 text-right">
                                        <Money value={debit} size="sm" />
                                    </td>
                                    <td className="py-2 text-right">
                                        <Money value={credit} size="sm" />
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    );
                })}
            </CollapsibleContent>
        </Collapsible>
    );
}
