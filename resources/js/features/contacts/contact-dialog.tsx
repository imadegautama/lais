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
import { localPhone } from '@/features/contacts/phone';
import { store, update } from '@/routes/contacts';
import type { ContactPerson } from '@/types/domain';
import { ContactType, contactTypeLabels } from '@/types/enums';

/** Add a customer or supplier, or correct the one given. */
export function ContactDialog({
    contact,
    type = ContactType.Pelanggan,
    trigger,
}: {
    contact?: ContactPerson;
    /** The side a new contact starts on. */
    type?: ContactType;
    trigger: ReactNode;
}) {
    const id = useId();
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; phone: string; type?: ContactType }>(
        contact
            ? { name: contact.name, phone: localPhone(contact.phone) ?? '' }
            : { name: '', phone: '', type },
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
                        form.submit(contact ? update(contact.id) : store(), {
                            preserveScroll: true,
                            onSuccess: () => close(false),
                        });
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {contact ? `Ubah ${contact.name}` : 'Tambah kontak'}
                        </DialogTitle>
                        <DialogDescription>
                            {contact
                                ? 'Nama baru dipakai di catatan berikutnya dan di tagihannya.'
                                : 'Kontak juga muncul sendiri dari transaksi dan pesanan.'}
                        </DialogDescription>
                    </DialogHeader>

                    {!contact && (
                        <div className="flex flex-col gap-1.5">
                            <span
                                id={`${id}-type`}
                                className="text-sm font-medium"
                            >
                                Jenis kontak
                            </span>
                            <ChoiceGroup
                                labelledBy={`${id}-type`}
                                value={form.data.type ?? type}
                                options={Object.values(ContactType).map(
                                    (value) => ({
                                        value,
                                        label: contactTypeLabels[value],
                                    }),
                                )}
                                onChange={(next) => form.setData('type', next)}
                            />
                            <InputError message={form.errors.type} />
                        </div>
                    )}

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor={`${id}-name`}>Nama</Label>
                        <Input
                            id={`${id}-name`}
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            maxLength={120}
                            placeholder="Bu Sari"
                            autoFocus
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor={`${id}-phone`}>
                            Nomor WA (tidak wajib)
                        </Label>
                        <Input
                            id={`${id}-phone`}
                            type="tel"
                            inputMode="tel"
                            value={form.data.phone}
                            onChange={(event) =>
                                form.setData('phone', event.target.value)
                            }
                            placeholder="081234567890"
                        />
                        <InputError message={form.errors.phone} />
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
