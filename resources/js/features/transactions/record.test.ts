import { describe, expect, it } from 'vite-plus/test';
import type { MoneyAccountOption, RecordItem } from '@/types/domain';
import {
    cashEffect,
    chosenMoneyAccount,
    contactFieldFor,
    moneyImpact,
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
    account_id: '',
    useful_life_years: '4',
    method: 'tunai',
    occurred_at: '',
    lines: [],
    amount: null,
    paid_amount: null,
    contact: '',
    note: '',
    money_account_id: '',
    to_money_account_id: '',
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
        ['pindah_dana', '', 'tunai', 0],
        ['saldo_awal', 'saldo_kas', 'tunai', 30_000],
        ['saldo_awal', 'saldo_utang', 'kredit', 0],
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

const cashBox: MoneyAccountOption = {
    id: 1,
    kind: 'tunai',
    name: 'Kas tunai',
    balance: 200_000,
};
const bca: MoneyAccountOption = {
    id: 2,
    kind: 'non_tunai',
    name: 'BCA',
    balance: 50_000,
};
const gopay: MoneyAccountOption = {
    id: 3,
    kind: 'non_tunai',
    name: 'GoPay',
    balance: 0,
};

// The way TransactionService::moneyAccountFor() picks it.
describe('chosenMoneyAccount', () => {
    it('is Kas tunai for tunai and the only non-tunai rekening without asking', () => {
        expect(
            chosenMoneyAccount(form({ method: 'tunai' }), [cashBox, bca]),
        ).toBe(cashBox);
        expect(
            chosenMoneyAccount(form({ method: 'non_tunai' }), [cashBox, bca]),
        ).toBe(bca);
    });

    it('waits for a choice when there are several, and never takes Kas tunai as non-tunai', () => {
        const accounts = [cashBox, bca, gopay];

        expect(
            chosenMoneyAccount(form({ method: 'non_tunai' }), accounts),
        ).toBeNull();
        expect(
            chosenMoneyAccount(
                form({ method: 'non_tunai', money_account_id: '3' }),
                accounts,
            ),
        ).toBe(gopay);
        expect(
            chosenMoneyAccount(
                form({ method: 'non_tunai', money_account_id: '1' }),
                accounts,
            ),
        ).toBeNull();
        expect(
            chosenMoneyAccount(form({ method: 'kredit' }), accounts),
        ).toBeNull();
    });
});

describe('moneyImpact', () => {
    const accounts = [cashBox, bca, gopay];

    it('moves both sides of a transfer', () => {
        const transfer = form({
            type: 'pindah_dana',
            money_account_id: '1',
            to_money_account_id: '2',
        });

        expect(moneyImpact(transfer, 100_000, accounts)).toEqual([
            { account: cashBox, before: 200_000, after: 100_000 },
            { account: bca, before: 50_000, after: 150_000 },
        ]);
    });

    it('puts the part of a credit sale paid on the spot in Kas tunai', () => {
        const sale = form({ method: 'kredit', paid_amount: 10_000 });

        expect(moneyImpact(sale, 30_000, accounts)).toEqual([
            { account: cashBox, before: 200_000, after: 210_000 },
        ]);
    });

    it('shows nothing until the rekening is known or when no money moves', () => {
        expect(
            moneyImpact(
                form({ type: 'biaya', kind: 'sewa', method: 'non_tunai' }),
                5_000,
                accounts,
            ),
        ).toEqual([]);
        expect(
            moneyImpact(
                form({ type: 'biaya', kind: 'sewa', method: 'pribadi' }),
                5_000,
                accounts,
            ),
        ).toEqual([]);
        expect(
            moneyImpact(
                form({
                    type: 'biaya',
                    kind: 'sewa',
                    method: 'non_tunai',
                    money_account_id: '2',
                }),
                5_000,
                accounts,
            ),
        ).toEqual([{ account: bca, before: 50_000, after: 45_000 }]);
    });
});

describe('toPayload with rekening', () => {
    it('sends the chosen rekening only for non-tunai', () => {
        expect(
            toPayload(
                form({
                    type: 'modal',
                    kind: 'setor',
                    method: 'non_tunai',
                    money_account_id: '3',
                    amount: 1,
                }),
                false,
                new Set(),
            ),
        ).toMatchObject({ money_account_id: 3 });
        expect(
            toPayload(
                form({
                    type: 'modal',
                    kind: 'setor',
                    method: 'tunai',
                    money_account_id: '3',
                    amount: 1,
                }),
                false,
                new Set(),
            ),
        ).not.toHaveProperty('money_account_id');
    });

    it('sends a transfer as two rekening and an amount, without a method', () => {
        expect(
            toPayload(
                form({
                    type: 'pindah_dana',
                    money_account_id: '1',
                    to_money_account_id: '',
                    amount: 5_000,
                    note: ' setoran ',
                }),
                false,
                new Set(),
            ),
        ).toEqual({
            type: 'pindah_dana',
            money_account_id: 1,
            to_money_account_id: null,
            amount: 5_000,
            occurred_at: null,
            note: 'setoran',
            again: false,
        });
    });
});

describe('opening balances', () => {
    it('asks for a supplier or a customer that may be new', () => {
        expect(
            contactFieldFor('saldo_awal', 'saldo_utang', 'tunai'),
        ).toMatchObject({
            contactType: 'supplier',
            required: true,
            creatable: true,
        });
        expect(
            contactFieldFor('saldo_awal', 'saldo_piutang', 'tunai'),
        ).toMatchObject({
            contactType: 'pelanggan',
            required: true,
            creatable: true,
        });
        expect(contactFieldFor('saldo_awal', 'saldo_kas', 'tunai')).toBeNull();
    });

    it('sends the rekening for cash and a new contact by name, never a method', () => {
        const cash = form({
            type: 'saldo_awal',
            kind: 'saldo_kas',
            money_account_id: '2',
            amount: 500_000,
        });
        const debt = form({
            type: 'saldo_awal',
            kind: 'saldo_utang',
            contact: ' Toko Sumber ',
            amount: 300_000,
        });

        expect(toPayload(cash, false, new Set())).toEqual({
            type: 'saldo_awal',
            kind: 'saldo_kas',
            amount: 500_000,
            money_account_id: 2,
            occurred_at: null,
            note: null,
            again: false,
        });
        expect(toPayload(debt, false, new Set())).toMatchObject({
            kind: 'saldo_utang',
            contact_name: 'Toko Sumber',
        });
        expect(toPayload(debt, false, new Set())).not.toHaveProperty('method');
    });

    it('moves only the rekening of an opening cash balance', () => {
        const cash = form({
            type: 'saldo_awal',
            kind: 'saldo_kas',
            money_account_id: '1',
        });

        expect(moneyImpact(cash, 500_000, [cashBox, bca])).toEqual([
            { account: cashBox, before: 200_000, after: 700_000 },
        ]);
        expect(
            moneyImpact(
                form({ type: 'saldo_awal', kind: 'saldo_peralatan' }),
                500_000,
                [cashBox, bca],
            ),
        ).toEqual([]);
    });
});
