import { Info } from 'lucide-react';
import type { ReactNode } from 'react';
import { MetricInline } from '@/components/app/metric';
import { Money } from '@/components/app/money';
import { SectionCard } from '@/components/app/section-card';
import { formatQty, formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    ContactOption,
    MoneyAccountOption,
    RecordItem,
} from '@/types/domain';
import { PaymentMethod, TransactionKind, TransactionType } from '@/types/enums';
import {
    balanceAfter,
    cashEffect,
    moneyImpact,
    purchaseImpact,
    saleImpact,
    type RecordFormData,
} from './record';

/**
 * What saving will change, computed with the server's rules (lib/stock,
 * record.ts), so the owner sees it before saving.
 */
export function ImpactPanel({
    data,
    total,
    items,
    contact,
    moneyAccounts,
}: {
    data: RecordFormData;
    total: number;
    items: Map<string, RecordItem>;
    contact: ContactOption | null;
    moneyAccounts: MoneyAccountOption[];
}) {
    const { type, kind, method } = data;
    const cash = cashEffect(type, kind, method, total, data.paid_amount ?? 0);
    const moves = moneyImpact(data, total, moneyAccounts);
    const isTransfer = type === TransactionType.PindahDana;

    return (
        <SectionCard
            title="Dampak catatan ini"
            description="Dihitung langsung dari isian di kiri."
            tone="focus"
            className="lg:sticky lg:top-18"
        >
            <div className="flex flex-col gap-4" aria-live="polite">
                <div className="flex flex-col divide-y">
                    <MetricInline
                        label="Total"
                        value={<Money value={total} size="lg" />}
                    />
                    {type === TransactionType.Penjualan && (
                        <SaleImpact data={data} total={total} items={items} />
                    )}
                    {type === TransactionType.Pembelian &&
                        kind === TransactionKind.Barang && (
                            <PurchaseImpact data={data} items={items} />
                        )}
                    {type === TransactionType.UtangPiutang && contact && (
                        <MetricInline
                            label={`Sisa tagihan ${contact.name}`}
                            value={
                                <span className="num text-sm font-semibold">
                                    {formatRupiah(contact.balance)} →{' '}
                                    {formatRupiah(balanceAfter(contact, total))}
                                </span>
                            }
                            detail={
                                total > contact.balance ? (
                                    <span className="font-semibold text-destructive">
                                        Lebih dari sisa tagihan. Turunkan
                                        nominalnya.
                                    </span>
                                ) : balanceAfter(contact, total) === 0 ? (
                                    'Lunas'
                                ) : undefined
                            }
                        />
                    )}
                    {/* A transfer changes two rekening; anything else, at most one. */}
                    {isTransfer &&
                        moves.map(({ account, before, after }) => (
                            <MetricInline
                                key={account.id}
                                label={account.name}
                                value={
                                    <BalanceChange
                                        before={before}
                                        after={after}
                                    />
                                }
                            />
                        ))}
                    <MetricInline
                        label="Kas usaha"
                        value={
                            <Money
                                value={cash}
                                direction={
                                    cash > 0 ? 'in' : cash < 0 ? 'out' : 'none'
                                }
                            />
                        }
                        detail={
                            isTransfer ? (
                                'Uang hanya pindah tempat, jumlah uang usaha tetap.'
                            ) : moves[0] ? (
                                <>
                                    Saldo {moves[0].account.name}{' '}
                                    <BalanceChange
                                        before={moves[0].before}
                                        after={moves[0].after}
                                        size="xs"
                                    />
                                </>
                            ) : (
                                cashNote(type, method)
                            )
                        }
                    />
                </div>
                <ImpactNote
                    type={type}
                    kind={kind}
                    method={method}
                    total={total}
                />
            </div>
        </SectionCard>
    );
}

function SaleImpact({
    data,
    total,
    items,
}: {
    data: RecordFormData;
    total: number;
    items: Map<string, RecordItem>;
}) {
    const { hpp, changes } = saleImpact(data.lines, items);

    return (
        <>
            <MetricInline
                label="Estimasi HPP"
                value={<Money value={hpp} />}
                detail="Biaya rata-rata bahan dan barang yang keluar."
            />
            <MetricInline
                label="Estimasi laba kotor"
                value={<Money value={total - hpp} />}
            />
            {changes.length > 0 && (
                <div className="flex flex-col gap-1.5 py-3 last:pb-0">
                    <span className="text-sm text-muted-foreground">
                        Stok yang berkurang
                    </span>
                    <ul className="flex flex-col gap-1 text-sm">
                        {changes.map(({ item, qty, after }) => (
                            <li
                                key={item.id}
                                className="flex items-baseline justify-between gap-3"
                            >
                                <span className="min-w-0 truncate">
                                    {item.name}{' '}
                                    <span className="num text-muted-foreground">
                                        −{formatQty(qty, item.unit)}
                                    </span>
                                </span>
                                <span
                                    className={cn(
                                        'num shrink-0 text-xs',
                                        after < 0
                                            ? 'font-semibold text-destructive'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {after < 0
                                        ? `jadi minus ${formatQty(after, item.unit)}`
                                        : `sisa ${formatQty(after, item.unit)}`}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </>
    );
}

function PurchaseImpact({
    data,
    items,
}: {
    data: RecordFormData;
    items: Map<string, RecordItem>;
}) {
    const changes = purchaseImpact(data.lines, items);

    if (changes.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-1.5 py-3 last:pb-0">
            <span className="text-sm text-muted-foreground">
                Stok bertambah dan biaya rata-rata
            </span>
            <ul className="flex flex-col gap-1 text-sm">
                {changes.map(({ item, qty, before, average }) => (
                    <li key={item.id} className="flex flex-col">
                        <span>
                            {item.name}{' '}
                            <span className="num text-muted-foreground">
                                +{formatQty(qty, item.unit)}
                            </span>
                        </span>
                        <span className="num text-xs text-muted-foreground">
                            {before > 0
                                ? `${formatRupiah(Math.round(before))} → ${formatRupiah(Math.round(average))}/${item.unit}`
                                : `${formatRupiah(Math.round(average))}/${item.unit}`}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

/** "Rp200.000 → Rp100.000"; a balance going below zero is flagged, not blocked. */
function BalanceChange({
    before,
    after,
    size = 'sm',
}: {
    before: number;
    after: number;
    size?: 'sm' | 'xs';
}) {
    return (
        <span
            className={cn(
                'num',
                size === 'sm' ? 'text-sm font-semibold' : 'text-xs',
                after < 0 && 'font-semibold text-destructive',
            )}
        >
            {formatRupiah(before)} → {formatRupiah(after)}
            {after < 0 && ' (minus)'}
        </span>
    );
}

function cashNote(
    type: TransactionType,
    method: PaymentMethod,
): string | undefined {
    // Equipment and opening debts are not money.
    if (type === TransactionType.SaldoAwal) {
        return 'Bukan uang, jadi kas usaha tidak berubah.';
    }

    if (method === PaymentMethod.Pribadi) {
        return 'Dibayar pakai uang pribadi, kas usaha tidak berubah.';
    }

    if (method === PaymentMethod.Kredit) {
        return type === TransactionType.Penjualan
            ? 'Hanya yang sudah dibayar yang masuk kas.'
            : 'Kas baru berkurang saat utangnya dibayar.';
    }

    // Several rekening and none picked yet: the balance shows once one is.
    return method === PaymentMethod.NonTunai
        ? 'Pilih rekeningnya untuk melihat saldonya.'
        : undefined;
}

function ImpactNote({
    type,
    kind,
    method,
    total,
}: {
    type: TransactionType;
    kind: string;
    method: PaymentMethod;
    total: number;
}) {
    const note: ReactNode = (() => {
        if (kind === TransactionKind.SaldoUtang) {
            return 'Utang awal mengurangi modal awal dan tercatat sebagai utang ke supplier itu.';
        }

        if (type === TransactionType.SaldoAwal) {
            return 'Saldo awal menambah modal awal, bukan pendapatan. Laba tidak berubah.';
        }

        if (kind === TransactionKind.Prive) {
            return 'Mengambil uang untuk pribadi tidak mengurangi laba, tetapi mengurangi modal usaha.';
        }

        if (type === TransactionType.Modal) {
            return 'Setoran modal menambah kas usaha, bukan pendapatan.';
        }

        if (type === TransactionType.Biaya) {
            return (
                <>
                    Mengurangi laba <Money value={total} size="sm" />.
                    {method === PaymentMethod.Pribadi &&
                        ' Uang pribadinya dicatat sebagai tambahan modal.'}
                </>
            );
        }

        if (kind === TransactionKind.Peralatan) {
            return 'Peralatan dicatat sebagai aset usaha, tidak mengurangi laba.';
        }

        if (
            type === TransactionType.Pembelian &&
            method === PaymentMethod.Pribadi
        ) {
            return 'Uang pribadi yang dipakai belanja dicatat sebagai tambahan modal.';
        }

        if (
            type === TransactionType.Penjualan &&
            method === PaymentMethod.Kredit
        ) {
            return 'Sisa yang belum dibayar masuk daftar piutang pelanggan.';
        }

        return null;
    })();

    return note ? (
        <p className="flex gap-2 rounded-lg bg-secondary px-3 py-2.5 text-sm">
            <Info
                className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                aria-hidden
            />
            <span>{note}</span>
        </p>
    ) : null;
}
