import { useForm } from '@inertiajs/react';
import { useId, useState, type ReactNode } from 'react';
import { Money } from '@/components/app/money';
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
import { formatQty } from '@/lib/format';
import { parseCount, roundQty } from '@/lib/number';
import { adjustmentValue } from '@/lib/stock';
import { adjustStock } from '@/routes/items';
import type { ItemRow } from '@/types/domain';

type StockAdjustDialogProps = {
    item: Pick<ItemRow, 'id' | 'name' | 'unit' | 'stock' | 'stockValue'>;
    trigger: ReactNode;
};

/**
 * Single-item stock count (UI-PAGES C8). The difference is valued at the
 * average cost, the same way App\Services\Inventory::adjust() books it.
 */
export function StockAdjustDialog({ item, trigger }: StockAdjustDialogProps) {
    const id = useId();
    const [open, setOpen] = useState(false);
    const form = useForm({ physical_stock: '' });
    const physical = parseCount(form.data.physical_stock);
    const diff = physical === null ? 0 : roundQty(physical - item.stock);
    const value = physical === null ? 0 : adjustmentValue(item, physical);

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
                        // React bubbles events through the portal, so a host page's <form> would submit too.
                        event.stopPropagation();
                        // Sent as typed (comma as dot), so the server's own messages explain empty or minus.
                        form.transform((data) => ({
                            physical_stock: data.physical_stock
                                .trim()
                                .replace(',', '.'),
                        }));
                        form.submit(adjustStock(item.id), {
                            preserveScroll: true,
                            onSuccess: () => close(false),
                        });
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>Koreksi stok {item.name}</DialogTitle>
                        <DialogDescription>
                            Isi jumlah hasil hitung di rak atau gudang.
                            Selisihnya dicatat sebagai selisih persediaan.
                        </DialogDescription>
                    </DialogHeader>

                    <dl className="flex items-baseline justify-between gap-3 rounded-lg bg-secondary px-3 py-2 text-sm">
                        <dt className="text-muted-foreground">
                            Stok di sistem
                        </dt>
                        <dd className="num font-semibold">
                            {formatQty(item.stock, item.unit)}
                        </dd>
                    </dl>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor={id}>
                            Stok hasil hitung ({item.unit})
                        </Label>
                        <Input
                            id={id}
                            inputMode="decimal"
                            autoComplete="off"
                            autoFocus
                            value={form.data.physical_stock}
                            onChange={(event) =>
                                form.setData(
                                    'physical_stock',
                                    event.target.value,
                                )
                            }
                            aria-invalid={
                                Boolean(form.errors.physical_stock) || undefined
                            }
                            aria-describedby={`${id}-diff`}
                            className="num"
                        />
                        <InputError message={form.errors.physical_stock} />
                        <p
                            id={`${id}-diff`}
                            aria-live="polite"
                            className="text-xs text-muted-foreground"
                        >
                            {physical === null ? (
                                'Boleh pakai koma, misalnya 8,5.'
                            ) : diff === 0 ? (
                                'Sama dengan sistem, tidak ada yang berubah.'
                            ) : (
                                <>
                                    {diff < 0 ? 'Kurang' : 'Lebih'}{' '}
                                    {formatQty(Math.abs(diff), item.unit)},
                                    senilai{' '}
                                    <Money value={Math.abs(value)} size="sm" />
                                    {item.stock <= 0 &&
                                        diff > 0 &&
                                        '. Belum ada biaya rata-rata, jadi tercatat tanpa nilai.'}
                                </>
                            )}
                        </p>
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            disabled={physical === null || form.processing}
                        >
                            {form.processing ? 'Menyimpan…' : 'Simpan koreksi'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
