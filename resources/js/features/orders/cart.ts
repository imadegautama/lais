import type { ShopItem } from '@/types/domain';

/** Quantity per item id. Prices are never kept: the server reads the catalog. */
export type Cart = Record<number, number>;

export type CartLine = { item: ShopItem; qty: number; subtotal: number };

export const MAX_QTY = 999;
/** OrderRequest takes at most 20 different products. */
export const MAX_LINES = 20;
/** From this many left, the shop shows "Sisa N". */
export const LOW_LEFT = 5;

/** The most a customer may order: what is left (Orders::left), or 999 for a recipe product. */
export function maxQty(item: ShopItem): number {
    return Math.min(MAX_QTY, item.left ?? MAX_QTY);
}

export function setQty(cart: Cart, itemId: number, qty: number): Cart {
    const next = { ...cart };

    if (qty <= 0) {
        delete next[itemId];
    } else {
        next[itemId] = Math.min(MAX_QTY, Math.floor(qty));
    }

    return next;
}

/**
 * Lines in catalog order; items no longer orderable drop out, and a saved
 * quantity above what is left now is cut down to it.
 */
export function cartLines(cart: Cart, items: ShopItem[]): CartLine[] {
    return items
        .filter((item) => item.orderable && (cart[item.id] ?? 0) > 0)
        .map((item) => {
            const qty = Math.min(cart[item.id], maxQty(item));

            return { item, qty, subtotal: qty * item.price };
        });
}

export function cartTotal(lines: CartLine[]): number {
    return lines.reduce((sum, line) => sum + line.subtotal, 0);
}

export function cartCount(lines: CartLine[]): number {
    return lines.reduce((sum, line) => sum + line.qty, 0);
}

/**
 * The cart survives a reload of the shop (per shop, this tab only). Storage
 * can be blocked (private mode), so the cart then lives in memory only.
 */
export function loadCart(slug: string): Cart {
    try {
        const saved = JSON.parse(
            sessionStorage.getItem(`lais-cart:${slug}`) ?? '{}',
        ) as unknown;

        return typeof saved === 'object' && saved !== null
            ? Object.fromEntries(
                  Object.entries(saved).filter(
                      ([id, qty]) =>
                          Number(id) > 0 &&
                          Number.isInteger(qty) &&
                          (qty as number) > 0,
                  ),
              )
            : {};
    } catch {
        return {};
    }
}

export function saveCart(slug: string, cart: Cart): void {
    try {
        sessionStorage.setItem(`lais-cart:${slug}`, JSON.stringify(cart));
    } catch {
        // Storage blocked: the cart still works for this visit.
    }
}
