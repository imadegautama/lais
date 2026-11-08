import { router, useForm } from '@inertiajs/react';
import {
    Check,
    Copy,
    Download,
    ExternalLink,
    QrCode,
    Settings2,
} from 'lucide-react';
import { QRCodeCanvas } from 'qrcode.react';
import { useEffect, useId, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Field } from '@/components/app/field';
import { SwitchRow } from '@/components/app/switch-row';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { useClipboard } from '@/hooks/use-clipboard';
import { formErrors } from '@/lib/form';
import { shop as updateShop } from '@/routes/orders';
import type { OwnerShop } from '@/types/domain';
import { displayPhone } from './phone';

type Shop = OwnerShop;

/**
 * The shop's switches on the orders page (UI-PAGES C9), as one compact bar
 * above the list: open or closed, its link and printable QR, and where
 * customers reach and pay the owner. The list below gets the full width.
 */
export function ShopBar({ shop }: { shop: Shop }) {
    const id = useId();
    const [copied, copy] = useClipboard();

    return (
        <section
            aria-labelledby={`${id}-title`}
            className="flex flex-col gap-3 rounded-xl border bg-card p-4 shadow-card lg:flex-row lg:items-center lg:gap-6"
        >
            <div className="flex items-center justify-between gap-4 lg:justify-start">
                <div className="min-w-0">
                    <h2 id={`${id}-title`} className="text-sm font-semibold">
                        <label htmlFor={`${id}-open`}>
                            Terima pesanan online
                        </label>
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        {shop.open
                            ? 'Pesanan baru masuk ke tab Menunggu.'
                            : 'Toko bisa dilihat, tombol pesan dimatikan.'}
                    </p>
                </div>
                <Switch
                    id={`${id}-open`}
                    checked={shop.open}
                    onCheckedChange={(open) =>
                        router.patch(
                            updateShop.url(),
                            { accepting_orders: open },
                            { preserveScroll: true },
                        )
                    }
                />
            </div>
            <div className="flex min-w-0 flex-1 flex-col gap-2 border-t pt-3 lg:flex-row lg:items-center lg:justify-end lg:border-t-0 lg:border-l lg:pt-0 lg:pl-6">
                <p
                    className="min-w-0 truncate text-sm text-muted-foreground lg:flex-1"
                    title={shop.url}
                >
                    {shop.url}
                </p>
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={async () => {
                            if (!(await copy(shop.url))) {
                                toast.error(
                                    'Link tidak bisa disalin. Salin manual dari teks di sampingnya.',
                                );
                            }
                        }}
                    >
                        {copied === shop.url ? (
                            <Check aria-hidden />
                        ) : (
                            <Copy aria-hidden />
                        )}
                        {copied === shop.url ? 'Tersalin' : 'Salin link'}
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <a href={shop.url} target="_blank" rel="noreferrer">
                            <ExternalLink aria-hidden />
                            Buka toko
                        </a>
                    </Button>
                    <QrDialog shop={shop} />
                    <SettingsDialog shop={shop} />
                </div>
            </div>
        </section>
    );
}

/** A QR of the shop link, to print and put on the counter or table. */
function QrDialog({ shop }: { shop: Shop }) {
    const canvas = useRef<HTMLCanvasElement>(null);

    const download = () => {
        const node = canvas.current;

        if (!node) {
            return;
        }

        const link = document.createElement('a');
        link.download = `qr-toko-${shop.slug}.png`;
        link.href = node.toDataURL('image/png');
        link.click();
    };

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button type="button" variant="outline" size="sm">
                    <QrCode aria-hidden />
                    QR toko
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>QR toko {shop.name}</DialogTitle>
                    <DialogDescription>
                        Cetak dan tempel di meja atau etalase. Pelanggan
                        memindai untuk membuka toko.
                    </DialogDescription>
                </DialogHeader>
                <div className="flex flex-col items-center gap-2 rounded-lg border bg-white p-4">
                    <QRCodeCanvas
                        ref={canvas}
                        value={shop.url}
                        size={240}
                        marginSize={2}
                        level="M"
                        title={`QR link toko ${shop.name}`}
                    />
                    <p className="text-center text-sm font-semibold text-neutral-900">
                        Pesan di {shop.name}
                    </p>
                </div>
                <Button type="button" onClick={download}>
                    <Download aria-hidden />
                    Unduh PNG
                </Button>
            </DialogContent>
        </Dialog>
    );
}

/** The owner's WA number for "Hubungi penjual" and the QRIS image on the nota. */
function SettingsDialog({ shop }: { shop: Shop }) {
    const id = useId();
    const [open, setOpen] = useState(false);
    const form = useForm<{
        whatsapp: string;
        qris: File | null;
        remove_qris: boolean;
        online_payment: boolean;
    }>({
        whatsapp: shop.whatsapp ? displayPhone(shop.whatsapp) : '',
        qris: null,
        remove_qris: false,
        online_payment: shop.onlinePayment,
    });
    const errors = formErrors(form);
    const [preview, setPreview] = useState<string | null>(null);

    useEffect(() => {
        if (!form.data.qris) {
            setPreview(null);

            return;
        }

        const url = URL.createObjectURL(form.data.qris);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [form.data.qris]);

    const shown = form.data.remove_qris ? null : (preview ?? shop.qrisUrl);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    form.reset();
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button type="button" variant="outline" size="sm">
                    <Settings2 aria-hidden />
                    Pengaturan
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <form
                    className="flex flex-col gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        // A file goes up as multipart, which PATCH cannot carry: spoof it.
                        form.transform((data) => ({
                            ...data,
                            _method: 'patch',
                        }));
                        form.post(updateShop.url(), {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => setOpen(false),
                        });
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>Pengaturan toko</DialogTitle>
                        <DialogDescription>
                            Tampil di halaman toko dan nota pelanggan.
                        </DialogDescription>
                    </DialogHeader>
                    <Field
                        label="Nomor WhatsApp penjual"
                        htmlFor={`${id}-wa`}
                        error={errors.whatsapp}
                        hint="Untuk tombol Hubungi penjual. Kosongkan untuk menyembunyikannya."
                    >
                        <Input
                            id={`${id}-wa`}
                            type="tel"
                            inputMode="tel"
                            value={form.data.whatsapp}
                            onChange={(event) =>
                                form.setData('whatsapp', event.target.value)
                            }
                            placeholder="08…"
                            maxLength={20}
                            autoComplete="tel"
                        />
                    </Field>
                    <Field
                        label="Gambar QRIS"
                        htmlFor={`${id}-qris`}
                        error={errors.qris}
                        hint="Foto atau tangkapan layar QRIS dari bank, JPG/PNG maksimal 5 MB."
                    >
                        {shown && (
                            <img
                                src={shown}
                                alt="Pratinjau QRIS"
                                className="size-40 rounded-lg border bg-white object-contain"
                            />
                        )}
                        <Input
                            id={`${id}-qris`}
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={(event) => {
                                form.setData(
                                    'qris',
                                    event.target.files?.[0] ?? null,
                                );
                                form.setData('remove_qris', false);
                            }}
                        />
                    </Field>
                    {shop.qrisUrl &&
                        !form.data.remove_qris &&
                        !form.data.qris && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="w-fit text-destructive hover:text-destructive"
                                onClick={() =>
                                    form.setData('remove_qris', true)
                                }
                            >
                                Hapus QRIS
                            </Button>
                        )}
                    {shop.onlinePaymentReady && (
                        <SwitchRow
                            id={`${id}-online`}
                            label="Terima bayar online"
                            hint="Pelanggan bisa bayar lewat DOKU (mode uji) saat memesan. Uangnya dicatat sebagai DP di rekening Saldo DOKU."
                            checked={form.data.online_payment}
                            onChange={(value) =>
                                form.setData('online_payment', value)
                            }
                        />
                    )}
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
