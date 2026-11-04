import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Copy,
    ExternalLink,
    FileImage,
    ListChecks,
    MessageCircle,
    Package,
    ReceiptText,
    Send,
    User,
    Wallet,
} from 'lucide-react';
import { useId, useState } from 'react';
import { toast } from 'sonner';
import { OrderStatusBadge, PaymentStatusBadge } from '@/components/app/badges';
import { DetailRow } from '@/components/app/detail-row';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    NextStepButtons,
    OrderPaymentDialog,
    ReasonDialog,
} from '@/features/orders/order-actions';
import { OrderLines, OrderTimeline } from '@/features/orders/order-timeline';
import { displayPhone, waLink } from '@/features/orders/phone';
import { statusMessage } from '@/features/orders/vocabulary';
import { useClipboard } from '@/hooks/use-clipboard';
import { formatDate, formatRupiah } from '@/lib/format';
import { index as debtsIndex } from '@/routes/debts';
import { index } from '@/routes/orders';
import { show as showTransaction } from '@/routes/transactions';
import { orderFulfillmentLabels, paymentMethodLabels } from '@/types/enums';
import type { OrderShowProps } from '@/types/domain';

/** One order (UI-PAGES C10): its next step, the customer, and the money. */
export default function OrderShow({
    shop,
    order,
    notaUrl,
    moneyAccounts,
}: OrderShowProps) {
    const rest = Math.max(0, order.total - order.paid);
    const [copied, copy] = useClipboard();

    return (
        <>
            <Head title={`Pesanan ${order.code}`} />
            <div className="flex flex-col gap-4 p-4 md:gap-6 md:p-6">
                <Button variant="ghost" className="-ml-3 w-fit" asChild>
                    <Link href={index({ query: { status: order.status } })}>
                        <ArrowLeft aria-hidden />
                        Semua pesanan
                    </Link>
                </Button>
                <PageHeader
                    title={`Pesanan ${order.code}`}
                    meta={
                        <>
                            <OrderStatusBadge status={order.status} />
                            <PaymentStatusBadge status={order.paymentStatus} />
                        </>
                    }
                    description={`Masuk ${formatDate(order.createdAt, 'long')}, ${formatDate(order.createdAt, 'time')} WIB · ${order.source === 'toko' ? 'dari toko online' : 'dicatat pemilik'}`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <NextStepButtons order={order} />
                            {(order.status === 'diproses' ||
                                order.status === 'siap') && (
                                <ReasonDialog order={order} mode="batal" />
                            )}
                        </div>
                    }
                />

                <div className="grid grid-cols-1 gap-4 md:gap-6 lg:grid-cols-12">
                    <div className="flex min-w-0 flex-col gap-4 md:gap-6 lg:col-span-7">
                        <SectionCard title="Status" icon={ListChecks}>
                            <OrderTimeline order={order} />
                        </SectionCard>

                        <SectionCard title="Item" icon={Package}>
                            <OrderLines order={order} />
                            <div className="mt-3 flex items-baseline justify-between border-t pt-3">
                                <span className="text-sm font-medium">
                                    Total
                                </span>
                                <span className="num text-lg font-semibold">
                                    {formatRupiah(order.total)}
                                </span>
                            </div>
                        </SectionCard>

                        <SectionCard title="Pembayaran" icon={Wallet}>
                            <dl className="flex flex-col text-sm">
                                <DetailRow label="Total">
                                    {formatRupiah(order.total)}
                                </DetailRow>
                                <DetailRow label="Sudah dibayar">
                                    {formatRupiah(order.paid)}
                                </DetailRow>
                                <DetailRow label="Sisa">
                                    {formatRupiah(rest)}
                                </DetailRow>
                            </dl>
                            {order.paidElsewhere > 0 && (
                                <p className="mt-3 border-t pt-3 text-sm text-muted-foreground">
                                    {formatRupiah(order.paidElsewhere)} dibayar
                                    lewat{' '}
                                    <Link
                                        href={debtsIndex()}
                                        className="font-medium text-foreground underline underline-offset-4"
                                    >
                                        Utang-piutang
                                    </Link>
                                    .
                                </p>
                            )}
                            {order.payments.length > 0 && (
                                <ul className="mt-3 flex flex-col divide-y border-t">
                                    {order.payments.map((payment) => (
                                        <li
                                            key={payment.id}
                                            className="flex items-start justify-between gap-3 py-2.5 text-sm"
                                        >
                                            <div>
                                                <Link
                                                    href={showTransaction(
                                                        payment.id,
                                                    )}
                                                    className="font-medium hover:underline"
                                                >
                                                    {payment.label}
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
                                                    {formatDate(
                                                        payment.occurredAt,
                                                        'datetime',
                                                    )}{' '}
                                                    ·{' '}
                                                    {payment.moneyAccount ??
                                                        paymentMethodLabels[
                                                            payment.method
                                                        ]}
                                                </p>
                                            </div>
                                            <span className="num font-medium">
                                                {formatRupiah(payment.amount)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {order.status !== 'menunggu' &&
                                order.status !== 'ditolak' && (
                                    <div className="mt-3">
                                        <OrderPaymentDialog
                                            order={order}
                                            moneyAccounts={moneyAccounts}
                                        />
                                    </div>
                                )}
                            {order.status === 'menunggu' && (
                                <p className="mt-3 text-xs text-muted-foreground">
                                    Terima pesanannya dulu untuk mencatat DP.
                                </p>
                            )}
                            {order.saleId && (
                                <p className="mt-3 text-sm">
                                    Tercatat sebagai penjualan.{' '}
                                    <Link
                                        href={showTransaction(order.saleId)}
                                        className="font-medium text-primary-text underline underline-offset-4"
                                    >
                                        Lihat transaksi
                                    </Link>
                                </p>
                            )}
                        </SectionCard>
                    </div>

                    <div className="flex min-w-0 flex-col gap-4 md:gap-6 lg:col-span-5">
                        <SectionCard title="Pelanggan" icon={User}>
                            <dl className="flex flex-col text-sm">
                                <DetailRow label="Nama">
                                    {order.customerName}
                                </DetailRow>
                                <DetailRow label="WhatsApp">
                                    <a
                                        href={waLink(order.customerPhone, '')}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex items-center gap-1 text-primary-text underline underline-offset-4"
                                    >
                                        <MessageCircle
                                            className="size-4"
                                            aria-hidden
                                        />
                                        {displayPhone(order.customerPhone)}
                                    </a>
                                </DetailRow>
                                <DetailRow label="Cara terima">
                                    {orderFulfillmentLabels[order.fulfillment]}
                                </DetailRow>
                                {order.wantedAt && (
                                    <DetailRow label="Waktu">
                                        {formatDate(order.wantedAt, 'datetime')}{' '}
                                        WIB
                                    </DetailRow>
                                )}
                                {order.address && (
                                    <DetailRow label="Alamat">
                                        {order.address}
                                    </DetailRow>
                                )}
                                {order.note && (
                                    <DetailRow label="Catatan">
                                        {order.note}
                                    </DetailRow>
                                )}
                            </dl>
                        </SectionCard>

                        <SendNews
                            // A new status or payment writes a new message.
                            key={`${order.status}-${order.paid}`}
                            phone={order.customerPhone}
                            initial={statusMessage(order, shop.name, notaUrl)}
                        />

                        <SectionCard title="Nota pelanggan" icon={ReceiptText}>
                            <p
                                className="truncate text-sm text-muted-foreground"
                                title={notaUrl}
                            >
                                {notaUrl}
                            </p>
                            <div className="mt-2 flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={async () => {
                                        if (!(await copy(notaUrl))) {
                                            toast.error(
                                                'Link tidak bisa disalin.',
                                            );
                                        }
                                    }}
                                >
                                    {copied === notaUrl ? (
                                        <Check aria-hidden />
                                    ) : (
                                        <Copy aria-hidden />
                                    )}
                                    {copied === notaUrl
                                        ? 'Tersalin'
                                        : 'Salin link'}
                                </Button>
                                <Button variant="outline" size="sm" asChild>
                                    <a
                                        href={notaUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <ExternalLink aria-hidden />
                                        Buka nota
                                    </a>
                                </Button>
                            </div>
                        </SectionCard>
                    </div>
                </div>
            </div>
        </>
    );
}

/** "Kirim kabar ke pelanggan": an editable message sent from the owner's own WhatsApp. */
function SendNews({ phone, initial }: { phone: string; initial: string }) {
    const id = useId();
    const [text, setText] = useState(initial);

    return (
        <SectionCard title="Kirim kabar ke pelanggan" icon={Send}>
            <div className="flex flex-col gap-2">
                <Label htmlFor={id} className="sr-only">
                    Isi pesan
                </Label>
                <Textarea
                    id={id}
                    value={text}
                    onChange={(event) => setText(event.target.value)}
                    rows={6}
                    maxLength={1000}
                />
                <Button asChild className="w-fit">
                    <a
                        href={waLink(phone, text)}
                        target="_blank"
                        rel="noreferrer"
                    >
                        <MessageCircle aria-hidden />
                        Buka WhatsApp
                    </a>
                </Button>
            </div>
        </SectionCard>
    );
}
