import { useForm } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { QtyStepper } from '@/components/app/qty-stepper';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { focusFirstError, formErrors } from '@/lib/form';
import { formatRupiah } from '@/lib/format';
import { store } from '@/routes/orders';
import type { ShopItem } from '@/types/domain';
import { cartLines, cartTotal, MAX_QTY, setQty, type Cart } from './cart';
import { CustomerFields, emptyOrder, type OrderFormData } from './order-fields';

/**
 * "+ Pesanan": an order taken by phone or chat. It is accepted at once
 * (Orders::place), and may use products hidden from the shop.
 */
export function ManualOrderSheet({ items }: { items: ShopItem[] }) {
    const [open, setOpen] = useState(false);
    const [cart, setCart] = useState<Cart>({});
    const [query, setQuery] = useState('');
    const form = useForm<OrderFormData>(emptyOrder);
    const errors = formErrors(form);
    const lines = useMemo(() => cartLines(cart, items), [cart, items]);
    const visible = items.filter((item) =>
        item.name.toLowerCase().includes(query.trim().toLowerCase()),
    );
    const lineError = Object.entries(errors).find(([key]) =>
        key.startsWith('lines'),
    )?.[1];

    const close = (next: boolean) => {
        setOpen(next);

        if (!next) {
            setCart({});
            setQuery('');
            form.reset();
            form.clearErrors();
        }
    };

    return (
        <Sheet open={open} onOpenChange={close}>
            <SheetTrigger asChild>
                <Button type="button">
                    <Plus aria-hidden />
                    Pesanan
                </Button>
            </SheetTrigger>
            <SheetContent className="w-full gap-0 overflow-y-auto sm:max-w-lg">
                <form
                    className="flex min-h-full flex-col"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            lines: lines.map((line) => ({
                                item_id: line.item.id,
                                qty: line.qty,
                            })),
                        }));
                        form.post(store.url(), {
                            onSuccess: () => close(false),
                            onError: focusFirstError,
                        });
                    }}
                >
                    <SheetHeader className="border-b">
                        <SheetTitle>Pesanan baru</SheetTitle>
                        <SheetDescription>
                            Untuk pesanan lewat telepon atau chat. Langsung
                            berstatus Diproses.
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex flex-col gap-5 p-4">
                        <section
                            aria-label="Produk"
                            className="flex flex-col gap-3"
                        >
                            <div className="relative">
                                <Search
                                    aria-hidden
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    type="search"
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    placeholder="Cari produk"
                                    aria-label="Cari produk"
                                    className="pl-9"
                                />
                            </div>
                            <ul className="flex max-h-72 flex-col divide-y overflow-y-auto rounded-lg border px-3">
                                {visible.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex items-center justify-between gap-3 py-2"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {item.name}
                                            </p>
                                            <p className="num text-xs text-muted-foreground">
                                                {item.orderable
                                                    ? `${formatRupiah(item.price)} / ${item.unit}`
                                                    : 'Stok habis'}
                                            </p>
                                        </div>
                                        <QtyStepper
                                            label={item.name}
                                            value={cart[item.id] ?? 0}
                                            min={0}
                                            max={item.orderable ? MAX_QTY : 0}
                                            onChange={(qty) => {
                                                setCart((current) =>
                                                    setQty(
                                                        current,
                                                        item.id,
                                                        qty,
                                                    ),
                                                );
                                                form.clearErrors('lines');
                                            }}
                                        />
                                    </li>
                                ))}
                                {visible.length === 0 && (
                                    <li className="py-4 text-sm text-muted-foreground">
                                        {items.length === 0
                                            ? 'Belum ada produk berharga jual.'
                                            : 'Tidak ada produk dengan nama itu.'}
                                    </li>
                                )}
                            </ul>
                            {lineError && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {lineError}
                                </p>
                            )}
                        </section>

                        <CustomerFields form={form} />
                    </div>

                    <SheetFooter className="sticky bottom-0 border-t bg-card">
                        <Button
                            type="submit"
                            disabled={form.processing || lines.length === 0}
                        >
                            {form.processing
                                ? 'Menyimpan…'
                                : lines.length === 0
                                  ? 'Pilih produk dulu'
                                  : `Simpan pesanan · ${formatRupiah(cartTotal(lines))}`}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
