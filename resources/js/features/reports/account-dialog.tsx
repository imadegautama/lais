import { useForm } from '@inertiajs/react';
import { useId, useState, type ReactNode } from 'react';
import { ChoiceGroup } from '@/components/app/choice-group';
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
import { store, update } from '@/routes/accounts';
import type { ChartAccount } from '@/types/domain';
import { AccountType } from '@/types/enums';

/** Add an income or expense account, or rename one (the account given). */
export function AccountDialog({
    account,
    trigger,
}: {
    account?: ChartAccount;
    trigger: ReactNode;
}) {
    const id = useId();
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; type?: AccountType }>(
        account
            ? { name: account.name }
            : { name: '', type: AccountType.Beban },
    );

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
                                : 'Tambah akun'}
                        </DialogTitle>
                        <DialogDescription>
                            {account
                                ? 'Kode dan riwayatnya tetap. Nama baru dipakai di semua laporan.'
                                : 'Akun baru ikut masuk Laba rugi dan Neraca saldo. Kodenya dibuat otomatis.'}
                        </DialogDescription>
                    </DialogHeader>

                    {!account && (
                        <div className="flex flex-col gap-1.5">
                            <span
                                id={`${id}-type`}
                                className="text-sm font-medium"
                            >
                                Jenis akun
                            </span>
                            <ChoiceGroup
                                labelledBy={`${id}-type`}
                                value={form.data.type ?? AccountType.Beban}
                                options={[
                                    {
                                        value: AccountType.Beban,
                                        label: 'Beban',
                                    },
                                    {
                                        value: AccountType.Pendapatan,
                                        label: 'Pendapatan',
                                    },
                                ]}
                                onChange={(type) => form.setData('type', type)}
                            />
                            <InputError message={form.errors.type} />
                        </div>
                    )}

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor={id}>Nama akun</Label>
                        <Input
                            id={id}
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            maxLength={60}
                            placeholder="Beban Internet"
                            autoFocus
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
                            {form.processing ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
