import { describe, expect, it } from 'vite-plus/test';
import { initialItemForm, toItemPayload, withKind } from './item-form';

describe('toItemPayload', () => {
    it('sends no price or store switch for bahan, and opening stock only when adding', () => {
        const values = {
            ...initialItemForm(null, 'bahan'),
            name: 'Beras',
            unit: 'kg',
            price: 9_000,
            min_stock: '2,5',
            opening_stock: '10',
            opening_cost: 14_000,
        };

        expect(toItemPayload(values, false)).toEqual({
            kind: 'bahan',
            name: 'Beras',
            aliases: '',
            unit: 'kg',
            price: null,
            min_stock: 2.5,
            show_in_store: false,
            available: true,
            opening_stock: 10,
            opening_cost: 14_000,
        });
        expect(toItemPayload(values, true)).not.toHaveProperty('opening_stock');
    });

    it('sends the recipe for a recipe product and no stock fields', () => {
        const values = {
            ...initialItemForm(null, 'komposisi'),
            price: 15_000,
            recipe: [{ material_id: '1', qty: '0,15' }],
        };

        expect(toItemPayload(values, false)).toMatchObject({
            min_stock: null,
            recipe: [{ material_id: 1, qty: 0.15 }],
        });
        expect(toItemPayload(values, false)).not.toHaveProperty(
            'opening_stock',
        );
    });
});

describe('withKind', () => {
    it('swaps only the default unit', () => {
        expect(
            withKind(initialItemForm(null, 'produk'), 'komposisi').unit,
        ).toBe('porsi');
        expect(
            withKind(
                { ...initialItemForm(null, 'produk'), unit: 'kg' },
                'komposisi',
            ).unit,
        ).toBe('kg');
    });
});
