import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, PackageOpen, Search, ShoppingBag } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    cartCount,
    cartLines,
    cartTotal,
    loadCart,
    MAX_LINES,
    saveCart,
    setQty,
    type Cart,
} from '@/features/orders/cart';
import { CartPanel } from '@/features/orders/cart-panel';
import {
    CartSummary,
    CustomerFields,
    emptyOrder,
    type OrderFormData,
} from '@/features/orders/order-fields';
import { ProductRow } from '@/features/orders/product-row';
import { ShopIntro } from '@/features/orders/shop-intro';
import { focusFirstError, formErrors } from '@/lib/form';
import { formatRupiah } from '@/lib/format';
import { store } from '@/routes/shop';
import type { ShopShowProps } from '@/types/domain';

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
                    className="mx-auto flex w-full max-w-[640px] flex-col gap-5"
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
            <div className="grid gap-4 pb-24 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start lg:gap-6 lg:pb-0">
                <div className="flex min-w-0 flex-col gap-4">
                    <ShopIntro shop={shop} />

                    {/* The server refused the order (taken meanwhile): the cart dropped it, so say why here. */}
                    {errors.lines && (
                        <p
                            role="alert"
                            className="rounded-lg bg-destructive-soft px-4 py-3 text-sm text-destructive"
                        >
                            {errors.lines}
                        </p>
                    )}

                    {!shop.open && (
                        <p
                            role="status"
                            className="rounded-lg bg-warning-soft px-4 py-3 text-sm text-warning"
                        >
                            Toko sedang tidak menerima pesanan online.
                            Lihat-lihat dulu, atau hubungi penjual.
                        </p>
                    )}

                    {items.length === 0 ? (
                        <EmptyState
                            icon={PackageOpen}
                            title="Belum ada produk"
                            description="Penjual belum menampilkan produk di toko ini."
                        />
                    ) : (
                        <section
                            aria-labelledby="produk-judul"
                            className="flex flex-col gap-3"
                        >
                            <h2 id="produk-judul" className="sr-only">
                                Produk
                            </h2>
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
                                <ul className="grid gap-3 md:grid-cols-2">
                                    {visible.map((item) => (
                                        <ProductRow
                                            key={item.id}
                                            item={item}
                                            qty={
                                                lines.find(
                                                    (line) =>
                                                        line.item.id ===
                                                        item.id,
                                                )?.qty ?? 0
                                            }
                                            open={shop.open}
                                            full={
                                                lines.length >= MAX_LINES &&
                                                !cart[item.id]
                                            }
                                            onQty={(qty) =>
                                                changeQty(item.id, qty)
                                            }
                                        />
                                    ))}
                                </ul>
                            )}
                        </section>
                    )}
                </div>

                <CartPanel
                    lines={lines}
                    open={shop.open}
                    onQty={changeQty}
                    onNext={() => go('pesan')}
                />
            </div>

            {lines.length > 0 && (
                <div className="fixed inset-x-0 bottom-0 z-20 border-t bg-card shadow-bar lg:hidden">
                    <div className="mx-auto flex w-full max-w-6xl items-center gap-3 px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
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
