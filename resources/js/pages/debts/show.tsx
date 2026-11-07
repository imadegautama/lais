import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CircleCheck,
    Clock,
    FileImage,
    ReceiptText,
    UserRound,
    Wallet,
} from 'lucide-react';
import { LabelBadge } from '@/components/app/badges';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { PaymentDialog } from '@/components/app/payment-dialog';
import { SectionCard } from '@/components/app/section-card';
import { Button } from '@/components/ui/button';
import { RemindDialog } from '@/features/debts/remind-dialog';
import { ageLabel, ageTone, tabCopy } from '@/features/debts/vocabulary';
import { formatDate, formatRupiah } from '@/lib/format';
import { index, pay } from '@/routes/debts';
import { show as showOrder } from '@/routes/orders';
import { show as showTransaction } from '@/routes/transactions';
import { contactTypeLabels, paymentMethodLabels } from '@/types/enums';
import type { DebtShowProps } from '@/types/domain';

/**
 * One contact's tab: every bill with what is left on it, and the payments
 * that settled them, oldest bill first (Debts).
 */
export default function DebtShow({
    contact,
    balance,
    bills,
    payments,
    moneyAccounts,
    businessName,
}: DebtShowProps) {
    const tab = contact.type === 'pelanggan' ? 'piutang' : 'utang';
    const oldestOpen = bills.find((bill) => bill.remaining > 0);

    return (
        <>
            <Head title={contact.name} />
            <div className="flex flex-col gap-4 p-4 md:gap-6 md:p-6">
                <Button variant="ghost" className="-ml-3 w-fit" asChild>
                    <Link
                        href={index({
                            query: tab === 'utang' ? { jenis: 'utang' } : {},
                        })}
                    >
                        <ArrowLeft aria-hidden />
                        Utang-piutang
                    </Link>
                </Button>
                <PageHeader
                    title={contact.name}
                    meta={
                        <LabelBadge
                            icon={UserRound}
                            label={contactTypeLabels[contact.type]}
                        />
                    }
                    description={
                        balance > 0
                            ? `${tab === 'piutang' ? 'Belum dibayar' : 'Belum kita bayar'} ${formatRupiah(balance)}`
                            : 'Semua tagihan sudah lunas.'
                    }
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <PaymentDialog
                                title={`${tabCopy[tab].pay}: ${contact.name}`}
                                description={`Sisa ${formatRupiah(balance)}. Tagihan paling lama dilunasi lebih dulu.`}
                                rest={balance}
                                url={pay.url(contact.id)}
                                moneyAccounts={moneyAccounts}
                                label={tabCopy[tab].pay}
                                outgoing={tab === 'utang'}
                            />
                            {tab === 'piutang' &&
                                contact.phone &&
                                oldestOpen && (
                                    <RemindDialog
                                        name={contact.name}
                                        phone={contact.phone}
                                        balance={balance}
                                        since={oldestOpen.occurredAt}
                                        businessName={businessName}
                                    />
                                )}
                        </div>
                    }
                />

                <div className="grid grid-cols-1 gap-4 md:gap-6 lg:grid-cols-12">
                    <SectionCard
                        title="Tagihan"
                        icon={ReceiptText}
                        description="Yang belum lunas di atas, paling lama lebih dulu."
                        className="lg:col-span-7"
                    >
                        {bills.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada tagihan.
                            </p>
                        ) : (
                            <ul className="flex flex-col divide-y">
                                {bills.map((bill) => (
                                    <li
                                        key={bill.transactionId}
                                        className="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0"
                                    >
                                        <div className="min-w-0">
                                            <Link
                                                href={
                                                    bill.orderCode
                                                        ? showOrder(
                                                              bill.orderCode,
                                                          )
                                                        : showTransaction(
                                                              bill.transactionId,
                                                          )
                                                }
                                                className="line-clamp-2 text-sm font-medium hover:underline"
                                            >
                                                {bill.orderCode
                                                    ? `Pesanan ${bill.orderCode}`
                                                    : bill.description}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                {formatDate(
                                                    bill.occurredAt,
                                                    'short',
                                                )}{' '}
                                                · tagihan{' '}
                                                {formatRupiah(bill.amount)}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end gap-1">
                                            {bill.remaining > 0 ? (
                                                <>
                                                    <Money
                                                        value={bill.remaining}
                                                        size="md"
                                                    />
                                                    <LabelBadge
                                                        icon={Clock}
                                                        label={ageLabel(
                                                            bill.ageDays,
                                                        )}
                                                        tone={ageTone(
                                                            bill.ageDays,
                                                        )}
                                                    />
                                                </>
                                            ) : (
                                                <LabelBadge
                                                    icon={CircleCheck}
                                                    label="Lunas"
                                                    tone="success"
                                                />
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </SectionCard>

                    <SectionCard
                        title="Pembayaran"
                        icon={Wallet}
                        className="lg:col-span-5"
                    >
                        {payments.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada pembayaran.
                            </p>
                        ) : (
                            <ul className="flex flex-col divide-y">
                                {payments.map((payment) => (
                                    <li
                                        key={payment.transactionId}
                                        className="flex items-start justify-between gap-3 py-3 text-sm first:pt-0 last:pb-0"
                                    >
                                        <div>
                                            <Link
                                                href={showTransaction(
                                                    payment.transactionId,
                                                )}
                                                className="font-medium hover:underline"
                                            >
                                                {formatDate(
                                                    payment.occurredAt,
                                                    'short',
                                                )}
                                                {payment.hasProof && (
                                                    <span className="ml-1.5 inline-flex align-middle text-muted-foreground">
                                                        <FileImage
                                                            className="size-3.5"
                                                            aria-hidden
                                                        />
                                                        <span className="sr-only">
                                                            , ada bukti
                                                        </span>
                                                    </span>
                                                )}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                {payment.moneyAccount ??
                                                    paymentMethodLabels[
                                                        payment.method
                                                    ]}
                                                {payment.orderCode &&
                                                    ` · pesanan ${payment.orderCode}`}
                                            </p>
                                        </div>
                                        <span className="num font-medium">
                                            {formatRupiah(payment.amount)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </SectionCard>
                </div>
            </div>
        </>
    );
}
