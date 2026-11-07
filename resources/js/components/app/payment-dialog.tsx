import { useForm } from '@inertiajs/react';
import { Wallet } from 'lucide-react';
import { useId, useState } from 'react';
import { ChoiceGroup } from '@/components/app/choice-group';
import { ImagePicker } from '@/components/app/image-picker';
import { MoneyInput } from '@/components/app/money-input';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formErrors } from '@/lib/form';
import type { MoneyAccountOption } from '@/types/domain';

const fields = ['amount', 'method', 'money_account_id', 'proof'];

/**
 * "Catat pembayaran" on an order or a debt (PaymentRequest): how much, tunai
 * or transfer, and which rekening when there are several. The amount starts
 * at what is still unpaid and may not go above it.
 */
export function PaymentDialog({
    title,
    description,
    rest,
    url,
    moneyAccounts,
    label = 'Catat pembayaran',
    outgoing = false,
    size = 'default',
}: {
    title: string;
    description: string;
    rest: number;
    url: string;
    moneyAccounts: MoneyAccountOption[];
    label?: string;
    /** Paying a supplier: the money leaves the rekening. */
    outgoing?: boolean;
    size?: 'default' | 'sm';
}) {
    const id = useId();
    const [open, setOpen] = useState(false);
    const form = useForm<{
        amount: number | null;
        method: 'tunai' | 'non_tunai';
        money_account_id: string;
        proof: File | null;
    }>({ amount: rest, method: 'tunai', money_account_id: '', proof: null });
    const errors = formErrors(form);
    // Refusals from the service (no open balance, a finished order) have no field of their own.
    const refusal = Object.entries(errors).find(
        ([key]) => !fields.includes(key),
    )?.[1];
    const banks = moneyAccounts.filter(
        (account) => account.kind === 'non_tunai',
    );

    const close = (next: boolean) => {
        setOpen(next);

        if (next) {
            // What is left now, not when the page first loaded.
            form.setData('amount', rest);
        } else {
            form.reset();
            form.clearErrors();
        }
    };

    return (
        <Dialog open={open} onOpenChange={close}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size={size}
                    disabled={rest === 0}
                >
                    <Wallet aria-hidden />
                    {label}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <form
                    className="flex flex-col gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const transfer = form.data.method === 'non_tunai';
                        form.transform((data) => ({
                            amount: data.amount,
                            method: data.method,
                            money_account_id:
                                transfer && data.money_account_id
                                    ? Number(data.money_account_id)
                                    : null,
                            // Only a transfer or QRIS comes with a screenshot.
                            ...(transfer && data.proof
                                ? { proof: data.proof }
                                : {}),
                        }));
                        form.post(url, {
                            forceFormData: transfer && form.data.proof !== null,
                            preserveScroll: true,
                            onSuccess: () => close(false),
                        });
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    <div className="flex flex-col gap-1.5">
                        <MoneyInput
                            label="Nominal"
                            value={form.data.amount}
                            onChange={(value) => form.setData('amount', value)}
                        />
                        <InputError message={errors.amount} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <span
                            id={`${id}-method`}
                            className="text-sm font-medium"
                        >
                            {outgoing ? 'Dibayar dengan' : 'Dibayar lewat'}
                        </span>
                        <ChoiceGroup
                            labelledBy={`${id}-method`}
                            value={form.data.method}
                            options={[
                                { value: 'tunai', label: 'Tunai' },
                                {
                                    value: 'non_tunai',
                                    label: 'Transfer / QRIS',
                                },
                            ]}
                            onChange={(value) => form.setData('method', value)}
                        />
                    </div>
                    {form.data.method === 'non_tunai' && banks.length > 1 && (
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor={`${id}-account`}>
                                {outgoing
                                    ? 'Dari rekening'
                                    : 'Masuk ke rekening'}
                            </Label>
                            <Select
                                value={form.data.money_account_id}
                                onValueChange={(value) =>
                                    form.setData('money_account_id', value)
                                }
                            >
                                <SelectTrigger id={`${id}-account`}>
                                    <SelectValue placeholder="Pilih rekening" />
                                </SelectTrigger>
                                <SelectContent>
                                    {banks.map((account) => (
                                        <SelectItem
                                            key={account.id}
                                            value={String(account.id)}
                                        >
                                            {account.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.money_account_id} />
                        </div>
                    )}
                    {form.data.method === 'non_tunai' && (
                        <div className="flex flex-col gap-1.5">
                            <span className="text-sm font-medium">
                                Bukti transfer{' '}
                                <span className="font-normal text-muted-foreground">
                                    (opsional)
                                </span>
                            </span>
                            <ImagePicker
                                current={null}
                                file={form.data.proof}
                                error={errors.proof}
                                onFile={(file) => form.setData('proof', file)}
                                onRemove={() => form.setData('proof', null)}
                                maxSide={1600}
                                alt="Pratinjau bukti transfer"
                                fit="contain"
                                noun="bukti"
                            />
                        </div>
                    )}
                    {refusal && (
                        <p
                            role="alert"
                            className="rounded-lg bg-destructive-soft px-3 py-2 text-sm text-destructive"
                        >
                            {refusal}
                        </p>
                    )}
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Kembali
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Menyimpan…'
                                : 'Simpan pembayaran'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
