import { CircleCheck, CircleX, MessageCircle } from 'lucide-react';
import { LabelBadge } from '@/components/app/badges';
import { Button } from '@/components/ui/button';
import type { ShopInfo } from '@/types/domain';
import { waLink } from './phone';
import { askSellerMessage } from './vocabulary';

/** What a customer should know before ordering: no account, the seller confirms on WA. */
const steps = (onlinePayment: boolean) => [
    { title: 'Pilih produk', detail: 'Tekan + pada yang mau dipesan.' },
    { title: 'Kirim pesanan', detail: 'Nama dan nomor WA saja, tanpa akun.' },
    {
        title: 'Penjual konfirmasi',
        detail: onlinePayment
            ? 'Lewat WA. Bayar online saat memesan, atau tunai dan QRIS.'
            : 'Lewat WA. Bayar tunai atau QRIS.',
    },
];

/** The top of the shop (UI-PAGES A2): who sells, whether it takes orders, and how ordering works. */
export function ShopIntro({ shop }: { shop: ShopInfo }) {
    return (
        <section
            aria-labelledby="toko-nama"
            className="rounded-xl border bg-card p-4 shadow-card sm:p-5"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <h1
                        id="toko-nama"
                        className="text-2xl font-semibold tracking-tight"
                    >
                        {shop.name}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {[shop.category, 'Ambil sendiri atau diantar']
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {shop.open ? (
                        <LabelBadge
                            icon={CircleCheck}
                            label="Buka"
                            tone="success"
                        />
                    ) : (
                        <LabelBadge
                            icon={CircleX}
                            label="Tutup"
                            tone="danger"
                        />
                    )}
                    {shop.whatsapp && (
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={waLink(
                                    shop.whatsapp,
                                    askSellerMessage(null),
                                )}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <MessageCircle aria-hidden />
                                Hubungi penjual
                            </a>
                        </Button>
                    )}
                </div>
            </div>

            <h2 className="mt-4 border-t pt-4 text-sm font-semibold">
                Cara pesan
            </h2>
            <ol className="mt-3 grid grid-cols-3 gap-3">
                {steps(shop.onlinePayment).map((step, index) => (
                    <li
                        key={step.title}
                        className="flex flex-col gap-2 sm:flex-row sm:gap-3"
                    >
                        <span
                            aria-hidden
                            className="num flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-soft text-sm font-semibold text-primary-text"
                        >
                            {index + 1}
                        </span>
                        <p className="text-sm">
                            <span className="block font-medium">
                                {step.title}
                            </span>
                            <span className="text-xs text-muted-foreground sm:text-sm">
                                {step.detail}
                            </span>
                        </p>
                    </li>
                ))}
            </ol>
        </section>
    );
}
