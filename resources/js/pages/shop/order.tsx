import { Head, usePoll } from '@inertiajs/react';
import { Download, MessageCircle, Printer, QrCode } from 'lucide-react';
import { useEffect, type ReactNode } from 'react';
import { PaymentStatusBadge } from '@/components/app/badges';
import { DetailRow } from '@/components/app/detail-row';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { saveCart } from '@/features/orders/cart';
import { OnlinePay } from '@/features/orders/online-pay';
import { OrderLines, OrderTimeline } from '@/features/orders/order-timeline';
import { waLink } from '@/features/orders/phone';
import { askSellerMessage } from '@/features/orders/vocabulary';
import { formatDate, formatRupiah, zoneLabel } from '@/lib/format';
import { orderFulfillmentLabels } from '@/types/enums';
import type { ShopOrderProps } from '@/types/domain';

/**
 * The customer's nota (UI-PAGES A4), reached by its token only. It checks
 * for news every 30 seconds until the order is over.
 */
export default function ShopOrder({ shop, order }: ShopOrderProps) {
    const rest = Math.max(0, order.total - order.paid);
    const over =
        order.status === 'ditolak' ||
        (order.status === 'selesai' && rest === 0);
    const { stop } = usePoll(30_000, { only: ['order'] });
    // Online payment and its own order statuses, while the order is still open.
    const payOnline =
        shop.onlinePayment &&
        ['menunggu', 'diproses', 'siap'].includes(order.status);

    // The order is placed, also when the customer went to DOKU first: the shop's cart is done.
    useEffect(() => saveCart(shop.slug, {}), [shop.slug]);

    useEffect(() => {
        if (over) {
            stop();
        }
    }, [over, stop]);

    return (
        <>
            <Head title={`Pesanan ${order.code}`} />
            <div className="mx-auto flex w-full max-w-[640px] flex-col gap-4">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {shop.name} · {formatDate(order.createdAt, 'datetime')}{' '}
                        {zoneLabel()}
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Pesanan {order.code}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Atas nama {order.customerName}
                    </p>
                </div>

                <Card title="Status">
                    <OrderTimeline order={order} />
                </Card>

                <Card title="Rincian">
                    <OrderLines order={order} />
                    <dl className="mt-3 flex flex-col gap-1.5 border-t pt-3 text-sm">
                        <Row
                            label="Total"
                            value={formatRupiah(order.total)}
                            strong
                        />
                        <Row
                            label="Sudah dibayar"
                            value={formatRupiah(order.paid)}
                        />
                        <Row label="Sisa" value={formatRupiah(rest)} />
                    </dl>
                    <div className="mt-3">
                        <PaymentStatusBadge status={order.paymentStatus} />
                    </div>
                </Card>

                {order.status !== 'ditolak' && rest > 0 && (
                    <Card title="Bayar">
                        <p className="text-sm">
                            Yang perlu dibayar{' '}
                            <span className="num text-lg font-semibold">
                                {formatRupiah(rest)}
                            </span>
                        </p>
                        {payOnline && <OnlinePay order={order} />}
                        {order.status === 'menunggu' ? (
                            // Paying the seller before they accept risks paying for an order that is refused.
                            <p className="mt-2 text-sm text-muted-foreground">
                                {payOnline
                                    ? 'Atau tunggu penjual menerima pesanan ini, lalu bayar tunai atau QRIS ke penjual.'
                                    : 'Tunggu penjual menerima pesanan ini dulu, baru bayar. Halaman ini berubah sendiri.'}
                            </p>
                        ) : shop.qrisUrl ? (
                            <div className="mt-3 flex flex-col gap-3 sm:flex-row sm:items-start">
                                <Dialog>
                                    <DialogTrigger asChild>
                                        <button
                                            type="button"
                                            className="w-40 shrink-0 overflow-hidden rounded-lg border bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none print:w-32"
                                            aria-label="Perbesar QRIS"
                                        >
                                            <img
                                                src={shop.qrisUrl}
                                                alt={`QRIS ${shop.name}`}
                                                width={160}
                                                height={160}
                                                className="aspect-square w-full object-contain"
                                            />
                                        </button>
                                    </DialogTrigger>
                                    <DialogContent className="sm:max-w-md">
                                        <DialogHeader>
                                            <DialogTitle>
                                                QRIS {shop.name}
                                            </DialogTitle>
                                            <DialogDescription>
                                                Pindai dengan aplikasi bank atau
                                                dompet digital.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <img
                                            src={shop.qrisUrl}
                                            alt={`QRIS ${shop.name}`}
                                            className="w-full rounded-lg border bg-white object-contain"
                                        />
                                        <Button variant="outline" asChild>
                                            <a
                                                href={shop.qrisUrl}
                                                download={`qris-${shop.slug}`}
                                            >
                                                <Download aria-hidden />
                                                Unduh QRIS
                                            </a>
                                        </Button>
                                    </DialogContent>
                                </Dialog>
                                <ol className="flex list-decimal flex-col gap-1 pl-5 text-sm">
                                    <li>
                                        Pindai QRIS dengan aplikasi bank atau
                                        dompet digital.
                                    </li>
                                    <li>
                                        Isi nominal {formatRupiah(rest)}, atau
                                        sebagian sebagai DP.
                                    </li>
                                    <li>
                                        Kirim bukti bayar ke penjual lewat
                                        WhatsApp.
                                    </li>
                                </ol>
                            </div>
                        ) : (
                            <p className="mt-2 flex items-start gap-2 text-sm text-muted-foreground">
                                <QrCode
                                    className="mt-0.5 size-4 shrink-0"
                                    aria-hidden
                                />
                                {shop.whatsapp
                                    ? 'Bayar langsung ke penjual, atau tanyakan cara bayarnya lewat tombol Hubungi penjual.'
                                    : 'Bayar langsung ke penjual saat pesanan diambil atau diantar.'}
                            </p>
                        )}
                        <p className="mt-3 text-xs text-muted-foreground">
                            {payOnline
                                ? 'Bayar online tercatat sendiri. Bayar tunai atau QRIS dikonfirmasi penjual.'
                                : 'Konfirmasi pembayaran dilakukan penjual.'}{' '}
                            Halaman ini ikut berubah setelah pembayaran dicatat.
                        </p>
                    </Card>
                )}

                <Card title={orderFulfillmentLabels[order.fulfillment]}>
                    {order.wantedAt || order.address || order.note ? (
                        <dl className="flex flex-col text-sm">
                            {order.wantedAt && (
                                <DetailRow label="Waktu">
                                    {formatDate(order.wantedAt, 'datetime')}{' '}
                                    {zoneLabel()}
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
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Penjual akan mengabari waktunya.
                        </p>
                    )}
                </Card>

                <div className="flex flex-wrap gap-2 print:hidden">
                    {shop.whatsapp && (
                        // One primary on the nota: Bayar online takes it while it shows.
                        <Button
                            asChild
                            variant={
                                payOnline && rest > 0 ? 'outline' : 'default'
                            }
                        >
                            <a
                                href={waLink(
                                    shop.whatsapp,
                                    askSellerMessage(order),
                                )}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <MessageCircle aria-hidden />
                                Hubungi penjual
                            </a>
                        </Button>
                    )}
                    <Button
                        variant="outline"
                        type="button"
                        onClick={() => window.print()}
                    >
                        <Printer aria-hidden />
                        Simpan / cetak nota
                    </Button>
                </div>
            </div>
        </>
    );
}

function Card({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="rounded-xl border bg-card p-4 shadow-card print:shadow-none">
            <h2 className="mb-3 text-sm font-semibold">{title}</h2>
            {children}
        </section>
    );
}

function Row({
    label,
    value,
    strong = false,
}: {
    label: string;
    value: string;
    strong?: boolean;
}) {
    return (
        <div className="flex justify-between gap-3">
            <dt className={strong ? 'font-medium' : 'text-muted-foreground'}>
                {label}
            </dt>
            <dd className={`num ${strong ? 'font-semibold' : ''}`}>{value}</dd>
        </div>
    );
}
