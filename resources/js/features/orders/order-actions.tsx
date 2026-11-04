import { router, useForm } from '@inertiajs/react';
import { Ban, Check, CircleX } from 'lucide-react';
import { useId, useState } from 'react';
import { toast } from 'sonner';
import { PaymentDialog } from '@/components/app/payment-dialog';
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
import { formErrors } from '@/lib/form';
import { formatRupiah } from '@/lib/format';
import { accept, cancel, complete, pay, ready, reject } from '@/routes/orders';
import type { MoneyAccountOption, OwnerOrder } from '@/types/domain';
import { nextAction } from './vocabulary';

/** Refusals from Orders arrive under "order"; shown as a toast-free line. */
function OrderError({ message }: { message?: string }) {
    return message ? (
        <p
            role="alert"
            className="rounded-lg bg-destructive-soft px-3 py-2 text-sm text-destructive"
        >
            {message}
        </p>
    ) : null;
}

/**
 * The next step for an order (Terima → Tandai siap → Selesai), plus Tolak
 * while it waits. Used on the list cards and the detail page.
 */
export function NextStepButtons({
    order,
    size = 'default',
}: {
    order: OwnerOrder;
    size?: 'default' | 'sm';
}) {
    const [busy, setBusy] = useState(false);
    const label = nextAction[order.status];
    const route = { menunggu: accept, diproses: ready, siap: complete }[
        order.status as 'menunggu' | 'diproses' | 'siap'
    ];

    if (!label || !route) {
        return null;
    }

    return (
        <>
            <Button
                type="button"
                size={size}
                disabled={busy}
                onClick={() =>
                    router.post(route(order.code), undefined, {
                        preserveScroll: true,
                        onStart: () => setBusy(true),
                        onFinish: () => setBusy(false),
                        onError: (errors) =>
                            toast.error(
                                errors.order ?? 'Gagal menyimpan. Coba lagi.',
                            ),
                    })
                }
            >
                <Check aria-hidden />
                {busy ? 'Menyimpan…' : label}
            </Button>
            {order.status === 'menunggu' && (
                <ReasonDialog order={order} mode="tolak" size={size} />
            )}
        </>
    );
}

/** Tolak (waiting) or Batalkan (accepted; its uang muka is returned). */
export function ReasonDialog({
    order,
    mode,
    size = 'default',
}: {
    order: OwnerOrder;
    mode: 'tolak' | 'batal';
    size?: 'default' | 'sm';
}) {
    const id = useId();
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });
    const errors = formErrors(form);
    const refusing = mode === 'tolak';

    const close = (next: boolean) => {
        setOpen(next);

        if (!next) {
            form.reset();
            form.clearErrors();
        }
    };

    return (
        <Dialog open={open} onOpenChange={close}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size={size}
                    className="text-destructive hover:text-destructive"
                >
                    {refusing ? <CircleX aria-hidden /> : <Ban aria-hidden />}
                    {refusing ? 'Tolak' : 'Batalkan pesanan'}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <form
                    className="flex flex-col gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.submit((refusing ? reject : cancel)(order.code), {
                            preserveScroll: true,
                            onSuccess: () => close(false),
                        });
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {refusing
                                ? `Tolak pesanan ${order.code}?`
                                : `Batalkan pesanan ${order.code}?`}
                        </DialogTitle>
                        <DialogDescription>
                            {refusing
                                ? 'Pelanggan melihat alasannya di nota. Kabari juga lewat WhatsApp.'
                                : order.paid > 0
                                  ? `Uang muka ${formatRupiah(order.paid)} dicatat kembali ke pelanggan, jadi kas berkurang sebesar itu.`
                                  : 'Pesanan berhenti di sini dan pelanggan melihat alasannya di nota.'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor={id}>
                            Alasan{' '}
                            <span className="font-normal text-muted-foreground">
                                (boleh dikosongkan)
                            </span>
                        </Label>
                        <Input
                            id={id}
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            placeholder="Bahan sedang habis"
                            maxLength={200}
                            autoComplete="off"
                        />
                        <InputError message={errors.reason} />
                    </div>
                    <OrderError message={errors.order} />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Kembali
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing}
                        >
                            {form.processing
                                ? 'Menyimpan…'
                                : refusing
                                  ? 'Tolak pesanan'
                                  : 'Batalkan pesanan'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * "Catat pembayaran" (C10): before Selesai it is uang muka, after it is a
 * payment of the rest.
 */
export function OrderPaymentDialog({
    order,
    moneyAccounts,
    size = 'default',
}: {
    order: OwnerOrder;
    moneyAccounts: MoneyAccountOption[];
    size?: 'default' | 'sm';
}) {
    const rest = Math.max(0, order.total - order.paid);

    return (
        <PaymentDialog
            title={`Catat pembayaran ${order.code}`}
            description={
                order.status === 'selesai'
                    ? `Pelunasan. Sisa yang belum dibayar ${formatRupiah(rest)}.`
                    : `Dicatat sebagai uang muka sampai pesanan selesai. Sisa ${formatRupiah(rest)}.`
            }
            rest={rest}
            url={pay.url(order.code)}
            moneyAccounts={moneyAccounts}
            size={size}
        />
    );
}
