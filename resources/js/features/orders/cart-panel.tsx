import { ShoppingBag } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { CartLine } from './cart';
import { CartSummary } from './order-fields';

/** The cart beside the list on wide screens; phones keep the bar at the bottom. */
export function CartPanel({
    lines,
    open,
    onQty,
    onNext,
}: {
    lines: CartLine[];
    open: boolean;
    onQty: (itemId: number, qty: number) => void;
    onNext: () => void;
}) {
    return (
        <aside
            aria-labelledby="keranjang-judul"
            className="sticky top-4 hidden rounded-xl border bg-card p-4 shadow-card lg:block"
        >
            <h2
                id="keranjang-judul"
                className="mb-3 flex items-center gap-2 text-base font-semibold"
            >
                <ShoppingBag
                    aria-hidden
                    className="size-4 text-muted-foreground"
                />
                Keranjang
            </h2>
            {lines.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Masih kosong. Tekan + pada produk yang mau dipesan.
                </p>
            ) : (
                <>
                    <CartSummary lines={lines} onQty={onQty} errors={{}} />
                    <Button
                        type="button"
                        className="mt-4 w-full"
                        disabled={!open}
                        onClick={onNext}
                    >
                        Lanjut pesan
                    </Button>
                </>
            )}
        </aside>
    );
}
