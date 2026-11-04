import { Head, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CircleCheck,
    CircleX,
    MessageCircle,
    PackageOpen,
    Search,
    ShoppingBag,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { LabelBadge } from '@/components/app/badges';
import { EmptyState } from '@/components/app/states';
import { QtyStepper } from '@/components/app/qty-stepper';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    cartCount,
    cartLines,
    cartTotal,
    loadCart,
    MAX_LINES,
    MAX_QTY,
    saveCart,
    setQty,
    type Cart,
} from '@/features/orders/cart';
import {
    CartSummary,
    CustomerFields,
    emptyOrder,
    type OrderFormData,
} from '@/features/orders/order-fields';
import { waLink } from '@/features/orders/phone';
import { askSellerMessage } from '@/features/orders/vocabulary';
import { focusFirstError, formErrors } from '@/lib/form';
import { formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';
import { store } from '@/routes/shop';
import type { ShopItem, ShopShowProps } from '@/types/domain';

/**
 * The shop (UI-PAGES A2 + A3): pick from the catalog, then one form to send
 * the order. Prices are shown here only; the server reads the catalog.
 */
export default function ShopShow({ shop, items }: ShopShowProps) {
    // Empty on the server render; the saved cart is read after mount, so hydration matches.
    const [cart, setCart] = useState<Cart>({});
    const [step, setStep] = useState<'katalog' | 'pesan'>('katalog');
    const [query, setQuery] = useState('');
    const form = useForm<OrderFormData>(emptyOrder);
    const errors = formErrors(form);
    const lines = useMemo(() => cartLines(cart, items), [cart, items]);
    const visible = items.filter((item) =>
        item.name.toLowerCase().includes(query.trim().toLowerCase()),
    );

    useEffect(() => setCart(loadCart(shop.slug)), [shop.slug]);

    const changeQty = (itemId: number, qty: number) => {
        // Saved on each change, never from an effect: StrictMode's second run would save the empty first render.
        setCart((current) => {
            const next = setQty(current, itemId, qty);
            saveCart(shop.slug, next);

            return next;
        });
        form.clearErrors('lines');
    };

    const go = (next: typeof step) => {
        setStep(next);
        window.scrollTo({ top: 0 });
    };

    const send = () => {
        form.transform((data) => ({
            ...data,
            lines: lines.map((line) => ({
                item_id: line.item.id,
                qty: line.qty,
            })),
        }));
        form.post(store.url(shop.slug), {
            // The nota page clears nothing itself, so the cart is emptied here.
            onSuccess: () => {
                setCart({});
                saveCart(shop.slug, {});
            },
            onError: focusFirstError,
        });
    };

    if (step === 'pesan' && lines.length > 0) {
        return (
            <>
                <Head title={`Pesan di ${shop.name}`} />
                <form
                    className="flex flex-col gap-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        send();
                    }}
                >
                    <div className="flex flex-col items-start gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            className="-ml-3"
                            onClick={() => go('katalog')}
                        >
                            <ArrowLeft aria-hidden />
                            Ubah pesanan
                        </Button>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Kirim pesanan
                        </h1>
                    </div>

                    <section
                        aria-label="Pesanan Anda"
                        className="rounded-xl border bg-card p-4 shadow-card"
                    >
                        <CartSummary
                            lines={lines}
                            onQty={changeQty}
                            errors={errors}
                        />
                    </section>

                    <section
                        aria-label="Data pemesan"
                        className="rounded-xl border bg-card p-4 shadow-card"
                    >
                        <CustomerFields form={form} honeypot />
                        {errors.website && (
                            <p
                                role="alert"
                                className="mt-3 text-sm text-destructive"
                            >
                                {errors.website}
                            </p>
                        )}
                    </section>

                    <div className="flex flex-col gap-2">
                        <p className="text-xs text-muted-foreground">
                            Data hanya dipakai untuk pesanan ini. Penjual akan
                            mengonfirmasi pesanan dan cara bayarnya.
                        </p>
                        <Button
                            type="submit"
                            size="lg"
                            disabled={form.processing || !shop.open}
                        >
                            {form.processing
                                ? 'Mengirim…'
                                : `Kirim pesanan · ${formatRupiah(cartTotal(lines))}`}
                        </Button>
                    </div>
                </form>
            </>
        );
    }

    return (
        <>
            <Head title={shop.name} />
            <div className="flex flex-col gap-4 pb-24">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    {shop.open ? (
                        <LabelBadge
                            icon={CircleCheck}
                            label="Buka"
                            tone="success"
                        />
                    ) : (
                        <LabelBadge
                            icon={CircleX}
                            label="Tutup"
                            tone="danger"
                        />
                    )}
                    {shop.whatsapp && (
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={waLink(
                                    shop.whatsapp,
                                    askSellerMessage(null),
                                )}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <MessageCircle aria-hidden />
                                Hubungi penjual
                            </a>
                        </Button>
                    )}
                </div>

                {!shop.open && (
                    <p
                        role="status"
                        className="rounded-lg bg-warning-soft px-4 py-3 text-sm text-warning"
                    >
                        Toko sedang tidak menerima pesanan online. Lihat-lihat
                        dulu, atau hubungi penjual.
                    </p>
                )}

                {items.length === 0 ? (
                    <EmptyState
                        icon={PackageOpen}
                        title="Belum ada produk"
                        description="Penjual belum menampilkan produk di toko ini."
                    />
                ) : (
                    <>
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
                        {visible.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                Tidak ada produk bernama “{query.trim()}”.
                            </p>
                        ) : (
                            <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {visible.map((item) => (
                                    <ProductCard
                                        key={item.id}
                                        item={item}
                                        qty={cart[item.id] ?? 0}
                                        open={shop.open}
                                        full={
                                            lines.length >= MAX_LINES &&
                                            !cart[item.id]
                                        }
                                        onQty={(qty) => changeQty(item.id, qty)}
                                    />
                                ))}
                            </ul>
                        )}
                    </>
                )}
            </div>

            {lines.length > 0 && (
                <div className="fixed inset-x-0 bottom-0 z-20 border-t bg-card shadow-bar">
                    <div className="mx-auto flex w-full max-w-[640px] items-center gap-3 px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
                        <ShoppingBag
                            aria-hidden
                            className="size-5 shrink-0 text-muted-foreground"
                        />
                        <p className="num min-w-0 flex-1 text-sm">
                            <span className="font-semibold">
                                {cartCount(lines)} item
                            </span>{' '}
                            · {formatRupiah(cartTotal(lines))}
                        </p>
                        <Button
                            type="button"
                            disabled={!shop.open}
                            onClick={() => go('pesan')}
                        >
                            Lanjut pesan
                        </Button>
                    </div>
                </div>
            )}
        </>
    );
}

function ProductCard({
    item,
    qty,
    open,
    full,
    onQty,
}: {
    item: ShopItem;
    qty: number;
    open: boolean;
    /** The order already has 20 different products. */
    full: boolean;
    onQty: (qty: number) => void;
}) {
    return (
        <li className="flex flex-col overflow-hidden rounded-xl border bg-card shadow-card">
            <div className="relative aspect-square bg-secondary">
                {item.photoUrl ? (
                    <img
                        src={item.photoUrl}
                        alt=""
                        width={320}
                        height={320}
                        loading="lazy"
                        decoding="async"
                        className={cn(
                            'size-full object-cover',
                            !item.orderable && 'opacity-50 grayscale',
                        )}
                    />
                ) : (
                    <span
                        aria-hidden
                        className="flex size-full items-center justify-center text-3xl font-semibold text-muted-foreground"
                    >
                        {item.name.slice(0, 1).toUpperCase()}
                    </span>
                )}
                {!item.orderable && (
                    <LabelBadge
                        icon={CircleX}
                        label="Habis"
                        className="absolute top-2 left-2 bg-card text-muted-foreground"
                    />
                )}
            </div>
            <div className="flex flex-1 flex-col gap-2 p-2.5">
                <div className="flex-1">
                    <h2
                        className={cn(
                            'line-clamp-2 text-sm font-medium',
                            !item.orderable && 'text-muted-foreground',
                        )}
                    >
                        {item.name}
                    </h2>
                    <p className="num text-sm font-semibold">
                        {formatRupiah(item.price)}
                        <span className="font-normal text-muted-foreground">
                            {' '}
                            / {item.unit}
                        </span>
                    </p>
                </div>
                {qty > 0 ? (
                    <QtyStepper
                        label={item.name}
                        value={qty}
                        min={0}
                        max={MAX_QTY}
                        onChange={onQty}
                        className="w-full justify-between"
                    />
                ) : (
                    <Button
                        type="button"
                        variant="outline"
                        className="w-full"
                        disabled={!item.orderable || !open || full}
                        onClick={() => onQty(1)}
                        aria-label={`${item.orderable ? 'Tambah' : 'Habis:'} ${item.name}`}
                    >
                        {item.orderable ? 'Tambah' : 'Habis'}
                    </Button>
                )}
            </div>
        </li>
    );
}
