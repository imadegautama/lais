import { describe, expect, it } from 'vite-plus/test';
import {
    emptyAmountRow,
    emptyProductRow,
    firstStepWithError,
    initialDraft,
    openingBalance,
    resumeIndex,
    toPayload,
    withExamples,
    type OnboardingDraft,
    type StarterRow,
} from './onboarding';

const draft = (patch: Partial<OnboardingDraft> = {}): OnboardingDraft => ({
    ...initialDraft({
        draft: null,
        name: 'Warung Ani',
        category: 'kuliner',
        accounts: [
            { id: 1, kind: 'tunai', name: 'Kas tunai' },
            { id: 2, kind: 'non_tunai', name: 'Rekening' },
        ],
        latestStart: '2026-10-17',
    }),
    ...patch,
});

const rice: StarterRow = {
    name: 'Beras',
    kind: 'bahan',
    unit: 'kg',
    price: null,
    min_stock: 5,
    opening_stock: 9,
    opening_cost: 14_000,
    aliases: [],
    recipe: [],
};

// Same numbers as tests/Feature/OnboardingTest.php.
describe('openingBalance', () => {
    it('makes modal awal what the business has minus what it owes', () => {
        const books = draft({
            accounts: [
                { id: 1, kind: 'tunai', name: 'Kas tunai', balance: 500_000 },
                { id: 2, kind: 'non_tunai', name: 'BCA', balance: 2_000_000 },
            ],
            equipment: [{ name: 'Kompor', amount: 1_500_000 }],
            products: withExamples([], [rice]),
            debts: [{ name: 'Toko Sumber', amount: 300_000 }],
            receivables: [{ name: 'Bu Sari', amount: 50_000 }],
        });

        expect(openingBalance(books, 10_000)).toEqual({
            cash: 2_500_000,
            equipment: 1_500_000,
            // 9 kg × Rp14.000, plus stock already recorded
            inventory: 126_000 + 10_000,
            receivables: 50_000,
            assets: 4_186_000,
            debts: 300_000,
            capital: 3_886_000,
        });
    });

    it('gives a recipe product no stock of its own', () => {
        const recipe = {
            ...emptyProductRow(),
            kind: 'komposisi' as const,
            opening_stock: '3',
            opening_cost: 1_000,
        };

        expect(openingBalance(draft({ products: [recipe] }), 0).inventory).toBe(
            0,
        );
    });
});

describe('withExamples', () => {
    it('adds the starter rows not already listed, marked as examples', () => {
        const mine = { ...emptyProductRow(), name: 'beras' };
        const rows = withExamples(
            [mine],
            [
                rice,
                { ...rice, name: 'Telur', unit: 'butir', opening_stock: 2.5 },
            ],
        );

        expect(rows.map((row) => row.name)).toEqual(['beras', 'Telur']);
        expect(rows[1]).toMatchObject({ example: true, opening_stock: '2,5' });
    });
});

describe('toPayload', () => {
    it('drops rows left empty, reads typed numbers, and keeps the example marker out', () => {
        const books = draft({
            equipment: [emptyAmountRow(), { name: 'Etalase', amount: 750_000 }],
            products: [emptyProductRow(), ...withExamples([], [rice])],
            debts: [emptyAmountRow()],
        });

        const payload = toPayload(books);

        expect(payload.equipment).toEqual([
            { name: 'Etalase', amount: 750_000 },
        ]);
        expect(payload.debts).toEqual([]);
        expect(payload.products).toEqual([{ ...rice, opening_stock: 9 }]);
        expect(payload.accounts[0]).toEqual({
            id: 1,
            name: 'Kas tunai',
            balance: null,
        });
    });

    it('still sends a half-filled row so the server can say what is missing', () => {
        const half = { ...emptyProductRow(), price: 5_000 };

        expect(toPayload(draft({ products: [half] })).products).toHaveLength(1);
    });
});

describe('resuming and errors', () => {
    it('picks up after the last saved step', () => {
        expect(resumeIndex(draft())).toBe(0);
        expect(resumeIndex(draft({ step: 'uang' }))).toBe(2);
        expect(resumeIndex(draft({ step: 'utang' }))).toBe(5);
    });

    it('sends the owner to the earliest step with an error', () => {
        expect(
            firstStepWithError({
                'products.2.unit': 'Isi satuannya.',
                'debts.0.amount': 'Isi nilainya.',
            }),
        ).toBe('produk');
        expect(firstStepWithError({ started_on: 'Paling lambat …' })).toBe(
            'usaha',
        );
        expect(firstStepWithError({})).toBeNull();
    });
});
