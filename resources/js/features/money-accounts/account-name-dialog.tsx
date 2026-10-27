import { useForm } from '@inertiajs/react';
import { useId, useState, type ReactNode } from 'react';
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
import { store, update } from '@/routes/money-accounts';
import type { MoneyAccountOption } from '@/types/domain';

/** Add a rekening, or rename one (the account given). */
export function AccountNameDialog({
    account,
    trigger,
}: {
    account?: MoneyAccountOption;
    trigger: ReactNode;
}) {
    const id = useId();
    const [open, setOpen] = useState(false);
    const form = useForm({ name: account?.name ?? '' });

    const close = (next: boolean) => {
        setOpen(next);

        if (!next) {
            form.reset();
            form.clearErrors();
        }
    };

    return (
        <Dialog open={open} onOpenChange={close}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <form
                    className="flex flex-col gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.submit(account ? update(account.id) : store(), {
                            preserveScroll: true,
                            onSuccess: () => close(false),
                        });
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {account
                                ? `Ganti nama ${account.name}`
                                : 'Tambah rekening'}
                        </DialogTitle>
                        <DialogDescription>
                            {account
                                ? 'Riwayat dan saldonya tetap. Nama baru dipakai di semua catatan.'
                                : 'Rekening bank atau dompet digital tempat uang usaha masuk, misalnya untuk QRIS atau transfer.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor={id}>Nama rekening</Label>
                        <Input
                            id={id}
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder="BCA, GoPay, ShopeePay"
                            maxLength={60}
                            autoComplete="off"
                            autoFocus
                            aria-invalid={
                                Boolean(form.errors.name) || undefined
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Menyimpan…'
                                : account
                                  ? 'Simpan nama'
                                  : 'Tambah rekening'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
