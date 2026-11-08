import { Form, router } from '@inertiajs/react';
import { CreditCard, ExternalLink, RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { pay } from '@/routes/shop';
import type { PublicOrder } from '@/types/domain';

/**
 * Bayar online on the nota (App\Services\Doku): continue an open DOKU page,
 * or open a new one for what is still unpaid. DOKU is in test mode for now.
 */
export function OnlinePay({ order }: { order: PublicOrder }) {
    const payment = order.onlinePayment;

    if (payment?.status === 'menunggu' && payment.url) {
        return (
            <div className="mt-3 flex flex-col gap-2">
                <p className="text-sm">
                    Pembayaran online belum selesai. Lanjutkan di halaman DOKU,
                    lalu kembali ke nota ini.
                </p>
                <div className="flex flex-wrap gap-2">
                    <Button asChild>
                        <a href={payment.url}>
                            <ExternalLink aria-hidden />
                            Lanjutkan bayar
                        </a>
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => router.reload({ only: ['order'] })}
                    >
                        <RefreshCw aria-hidden />
                        Cek pembayaran
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <Form {...pay.form(order.token)} className="mt-3 flex flex-col gap-2">
            {({ processing }) => (
                <>
                    {payment &&
                        (payment.status === 'kedaluwarsa' ||
                            payment.status === 'gagal') && (
                            <p className="text-sm text-muted-foreground">
                                Pembayaran online sebelumnya{' '}
                                {payment.status === 'gagal'
                                    ? 'gagal'
                                    : 'sudah lewat batas waktu'}
                                . Coba lagi di bawah.
                            </p>
                        )}
                    <Button
                        type="submit"
                        className="w-fit"
                        disabled={processing}
                    >
                        <CreditCard aria-hidden />
                        {processing ? 'Membuka DOKU…' : 'Bayar online'}
                    </Button>
                    <p className="text-xs text-muted-foreground">
                        QRIS, virtual account, atau e-wallet lewat DOKU (mode
                        uji).
                    </p>
                </>
            )}
        </Form>
    );
}
