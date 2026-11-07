import { describe, expect, it } from 'vite-plus/test';
import type { RecipeMaterial } from '@/types/domain';
import { margin, openingValue, recipeCost, suggestedPrice } from './cost';

// Same numbers as InventoryTest::test_recipe_cost_is_the_sum_of_ingredients_at_average_cost.
const materials = new Map<string, RecipeMaterial>(
    [14_000, 2_200, 18_000, 1_540, 1_000].map((averageCost, i) => [
        String(i + 1),
        {
            id: i + 1,
            kind: 'bahan',
            name: `Bahan ${i + 1}`,
            unit: 'pcs',
            averageCost,
        },
    ]),
);

describe('recipe cost', () => {
    it('sums the ingredients at their average cost', () => {
        const rows = ['0,15', '1', '0.02', '1', '1'].map((qty, i) => ({
            material_id: String(i + 1),
            qty,
        }));

        expect(recipeCost(rows, materials)).toBe(7_200);
        expect(margin(15_000, 7_200)).toEqual({ rupiah: 7_800, percent: 52 });
    });

    it('stays empty until a line has a cost', () => {
        expect(
            recipeCost([{ material_id: '', qty: '' }], materials),
        ).toBeNull();
        expect(margin(15_000, null)).toBeNull();
        expect(margin(0, 500)).toEqual({ rupiah: -500, percent: null });
    });
});

describe('suggested price', () => {
    // Same numbers as AssistantTest::test_a_price_for_a_margin_is_rounded_up_to_rp500.
    it('keeps the margin and rounds up to Rp500', () => {
        expect(suggestedPrice(7_200, 40)).toBe(12_000);
        expect(suggestedPrice(7_200, 30)).toBe(10_500);
        expect(suggestedPrice(7_200, 50)).toBe(14_500);
        expect(suggestedPrice(699, 30)).toBe(1_000);
    });
});

describe('opening value', () => {
    it('is quantity times cost, or nothing yet', () => {
        expect(openingValue(9, 14_000)).toBe(126_000);
        expect(openingValue(Number.NaN, 14_000)).toBeNull();
        expect(openingValue(9, null)).toBeNull();
    });
});
