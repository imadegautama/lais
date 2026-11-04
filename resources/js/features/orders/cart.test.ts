import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import type { ShopItem } from '@/types/domain';
import {
    cartCount,
    cartLines,
    cartTotal,
    loadCart,
    saveCart,
    setQty,
} from './cart';

const item = (id: number, price: number, orderable = true): ShopItem => ({
    id,
    name: `Produk ${id}`,
    unit: 'pcs',
    price,
    photoUrl: null,
    orderable,
});

describe('cart', () => {
    beforeEach(() => {
        // Tests run in Node, which has no sessionStorage.
        const store = new Map<string, string>();
        vi.stubGlobal('sessionStorage', {
            getItem: (key: string) => store.get(key) ?? null,
            setItem: (key: string, value: string) => store.set(key, value),
        });
    });

    it('adds, caps at 999, and removes at zero', () => {
        let cart = setQty({}, 1, 2);
        cart = setQty(cart, 2, 5000);
        expect(cart).toEqual({ 1: 2, 2: 999 });
        expect(setQty(cart, 1, 0)).toEqual({ 2: 999 });
    });

    it('totals the lines still orderable, in catalog order', () => {
        const items = [item(1, 4000), item(2, 10000), item(3, 5000, false)];
        const lines = cartLines({ 3: 1, 2: 1, 1: 3 }, items);

        expect(lines.map((line) => line.item.id)).toEqual([1, 2]);
        expect(cartTotal(lines)).toBe(22000);
        expect(cartCount(lines)).toBe(4);
    });

    it('keeps the cart per shop across a reload and ignores junk', () => {
        saveCart('warung-ani', { 1: 2 });
        expect(loadCart('warung-ani')).toEqual({ 1: 2 });
        expect(loadCart('toko-lain')).toEqual({});

        sessionStorage.setItem('lais-cart:rusak', '{"1":-3,"x":2,"2":1.5}');
        expect(loadCart('rusak')).toEqual({});
    });
});
