import { describe, expect, it } from 'vite-plus/test';
import type { RecordItem } from '@/types/domain';
import {
    cashEffect,
    contactFieldFor,
    purchaseImpact,
    recordTotal,
    saleImpact,
    toPayload,
    type RecordFormData,
} from './record';

const item = (
    patch: Partial<RecordItem> & Pick<RecordItem, 'id'>,
): RecordItem => ({
    kind: 'bahan',
    name: `Item ${patch.id}`,
    aliases: [],
    unit: 'pcs',
    price: null,
    stock: 0,
    stockValue: 0,
    averageCost: 0,
    recipe: [],
    ...patch,
});

const byId = (...items: RecordItem[]) =>
    new Map(items.map((entry) => [String(entry.id), entry]));

const form = (patch: Partial<RecordFormData>): RecordFormData => ({
    type: 'penjualan',
    kind: '',
    method: 'tunai',
    occurred_at: '',
    lines: [],
    amount: null,
    paid_amount: null,
    contact: '',
    note: '',
    ...patch,
});

// Same numbers as tests/Feature/TransactionServiceTest.php.
describe('saleImpact', () => {
    it('explodes a recipe and costs each ingredient at its average', () => {
        const rice = item({
            id: 1,
            stock: 10,
            stockValue: 140_000,
            unit: 'kg',
        });
        const egg = item({
            id: 2,
            stock: 30,
            stockValue: 66_000,
            unit: 'butir',
        });
        const friedRice = item({
            id: 3,
            kind: 'komposisi',
            price: 15_000,
            recipe: [
                { materialId: 1, qty: 0.15 },
                { materialId: 2, qty: 1 },
            ],
        });

        const impact = saleImpact(
            [{ item_id: '3', qty: '2', price: 15_000 }],
            byId(rice, egg, friedRice),
        );

        expect(impact.hpp).toBe(8_600);
        expect(
            impact.changes.map((change) => [
                change.item.id,
                change.qty,
                change.after,
            ]),
        ).toEqual([
            [1, 0.3, 9.7],
            [2, 2, 28],
        ]);
    });

    it('charges only the value left when a sale goes past zero', () => {
        const chips = item({
            id: 4,
            kind: 'produk',
            stock: 1,
            stockValue: 2_000,
        });

        const impact = saleImpact(
            [{ item_id: '4', qty: '3', price: 5_000 }],
            byId(chips),
        );

        expect(impact.hpp).toBe(2_000);
        expect(impact.changes[0].after).toBe(-2);
    });

    it('follows two lines that share an ingredient in order', () => {
        const egg = item({ id: 2, stock: 1, stockValue: 2_200 });
        const omelette = item({
            id: 5,
            kind: 'komposisi',
            recipe: [{ materialId: 2, qty: 1 }],
        });
        const friedRice = item({
            id: 6,
            kind: 'komposisi',
            recipe: [{ materialId: 2, qty: 1 }],
        });

        const impact = saleImpact(
            [
                { item_id: '5', qty: '1', price: 8_000 },
                { item_id: '6', qty: '1', price: 15_000 },
            ],
            byId(egg, omelette, friedRice),
        );

        // The first takes the last egg's value; the second sells past zero for free.
        expect(impact.hpp).toBe(2_200);
        expect(impact.changes).toHaveLength(1);
        expect(impact.changes[0].after).toBe(-1);
    });
});

describe('purchaseImpact', () => {
    it('shows the new moving average', () => {
        const egg = item({
            id: 2,
            stock: 30,
            stockValue: 60_000,
            averageCost: 2_000,
        });

        const [change] = purchaseImpact(
            [{ item_id: '2', qty: '30', price: 2_400 }],
            byId(egg),
        );

        expect(change.before).toBe(2_000);
        expect(change.average).toBe(2_200);
    });

    it('restarts the average at the purchase price after selling past zero', () => {
        const chips = item({ id: 4, stock: -2, stockValue: 0 });

        const [change] = purchaseImpact(
            [{ item_id: '4', qty: '10', price: 2_500 }],
            byId(chips),
        );

        expect(change.average).toBe(2_500);
    });
});

describe('cashEffect', () => {
    it.each([
        ['penjualan', '', 'tunai', 30_000],
        ['penjualan', '', 'kredit', 10_000],
        ['pembelian', 'barang', 'kredit', 0],
        ['biaya', 'listrik_air', 'pribadi', 0],
        ['biaya', 'sewa', 'non_tunai', -30_000],
        ['utang_piutang', 'terima_piutang', 'tunai', 30_000],
        ['modal', 'prive', 'tunai', -30_000],
    ] as const)('%s %s %s moves %i', (type, kind, method, expected) => {
        expect(cashEffect(type, kind, method, 30_000, 10_000)).toBe(expected);
    });
});

describe('contactFieldFor', () => {
    it('asks for a customer only on a credit sale', () => {
        expect(contactFieldFor('penjualan', '', 'tunai')).toBeNull();
        expect(contactFieldFor('penjualan', '', 'kredit')?.contactType).toBe(
            'pelanggan',
        );
    });

    it('offers only owing contacts when paying off', () => {
        const field = contactFieldFor('utang_piutang', 'bayar_utang', 'tunai');

        expect(field).toMatchObject({
            contactType: 'supplier',
            onlyOwing: true,
            creatable: false,
        });
    });
});

describe('toPayload', () => {
    it('sends lines for a sale and a new contact by name', () => {
        const values = form({
            method: 'kredit',
            lines: [{ item_id: '3', qty: '0,5', price: 15_000 }],
            contact: ' Bu Sari ',
        });

        expect(recordTotal(values)).toBe(7_500);
        expect(toPayload(values, false, new Set())).toEqual({
            type: 'penjualan',
            method: 'kredit',
            occurred_at: null,
            note: null,
            again: false,
            lines: [{ item_id: 3, qty: 0.5, price: 15_000 }],
            paid_amount: 0,
            contact_name: 'Bu Sari',
        });
    });

    it('sends an amount and a known contact by id, never a new name when paying off', () => {
        const payment = form({
            type: 'utang_piutang',
            kind: 'terima_piutang',
            amount: 8_000,
            contact: '7',
        });
        const unknown = form({
            type: 'utang_piutang',
            kind: 'terima_piutang',
            amount: 8_000,
            contact: 'Bu Baru',
        });

        expect(toPayload(payment, true, new Set(['7']))).toMatchObject({
            kind: 'terima_piutang',
            amount: 8_000,
            contact_id: 7,
            again: true,
        });
        expect(toPayload(unknown, false, new Set())).not.toHaveProperty(
            'contact_name',
        );
    });
});
