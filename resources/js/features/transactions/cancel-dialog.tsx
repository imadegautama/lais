import { useForm } from '@inertiajs/react';
import { Ban } from 'lucide-react';
import { useId, useState } from 'react';
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
import { cancel } from '@/routes/transactions';

/**
 * "Batalkan" on the detail page (UI-PAGES C4). The record stays, marked
 * cancelled; TransactionService::cancel() reverses its stock and journal.
 */
export function CancelDialog({ id }: { id: number }) {
    const inputId = useId();
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });
    // "transaction" carries the refusals: already cancelled, archived item, payments against it.
    const errors = formErrors(form);

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
                    className="text-destructive hover:text-destructive"
                >
                    <Ban aria-hidden />
                    Batalkan
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <form
                    className="flex flex-col gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.submit(cancel(id), {
                            preserveScroll: true,
                            onSuccess: () => close(false),
                        });
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>Batalkan catatan ini?</DialogTitle>
                        <DialogDescription>
                            Stok, kas, dan utang-piutangnya dikembalikan seperti
                            sebelum dicatat. Catatannya tetap ada dengan tanda
                            Dibatalkan.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor={inputId}>
                            Alasan{' '}
                            <span className="font-normal text-muted-foreground">
                                (boleh dikosongkan)
                            </span>
                        </Label>
                        <Input
                            id={inputId}
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            placeholder="Tercatat dua kali"
                            maxLength={200}
                            autoComplete="off"
                            aria-invalid={Boolean(errors.reason) || undefined}
                        />
                        <InputError message={errors.reason} />
                    </div>

                    {errors.transaction && (
                        <p
                            role="alert"
                            className="rounded-lg bg-destructive-soft px-3 py-2 text-sm text-destructive"
                        >
                            {errors.transaction}
                        </p>
                    )}

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
                                ? 'Membatalkan…'
                                : 'Batalkan catatan'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
