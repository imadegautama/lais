import { describe, expect, it } from 'vite-plus/test';
import {
    adjustmentValue,
    afterStockIn,
    afterStockOut,
    averageCost,
    stockedParts,
    stockOutCost,
} from './stock';

// Same numbers as tests/Feature/TransactionServiceTest.php and InventoryTest.php.
describe('selling', () => {
    it('takes the average cost', () => {
        expect(stockOutCost({ stock: 10, stockValue: 25_000 }, 3)).toBe(7_500);
    });

    it('lets the last units take what is left and units past zero go free', () => {
        const chips = { stock: 1, stockValue: 2_000 };

        expect(stockOutCost(chips, 3)).toBe(2_000);
        expect(afterStockOut(chips, 3)).toEqual({ stock: -2, stockValue: 0 });
        expect(stockOutCost({ stock: -2, stockValue: 0 }, 1)).toBe(0);
    });
});

describe('buying', () => {
    it('moves the average', () => {
        const eggs = afterStockIn(
            { stock: 30, stockValue: 60_000 },
            30,
            72_000,
        );

        expect(averageCost(eggs)).toBe(2_200);
    });

    it('restarts the average after selling past zero and leaves the gap as HPP', () => {
        const chips = afterStockIn({ stock: -2, stockValue: 0 }, 10, 25_000);

        expect(chips).toEqual({ stock: 8, stockValue: 20_000 });
        expect(averageCost(chips)).toBe(2_500);
    });
});

describe('counting', () => {
    it('values the difference at the average and clears to zero exactly', () => {
        const rice = { stock: 10, stockValue: 140_000 };
        const sugar = { stock: 3, stockValue: 48_999 };

        expect(adjustmentValue(rice, 8.5)).toBe(-21_000);
        expect(adjustmentValue(sugar, 0)).toBe(-48_999);
        expect(adjustmentValue(rice, 10)).toBe(0);
    });
});

describe('recipes', () => {
    it('explode into ingredients times the quantity', () => {
        const friedRice = {
            id: 9,
            kind: 'komposisi' as const,
            recipe: [
                { materialId: 1, qty: 0.15 },
                { materialId: 2, qty: 1 },
            ],
        };

        expect(stockedParts(friedRice, 2)).toEqual([
            { id: 1, qty: 0.3 },
            { id: 2, qty: 2 },
        ]);
        expect(stockedParts({ id: 5, kind: 'produk', recipe: [] }, 3)).toEqual([
            { id: 5, qty: 3 },
        ]);
    });
});
