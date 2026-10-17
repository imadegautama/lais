import { parseDecimal, roundHalfAway } from '@/lib/number';
import type { RecipeMaterial } from '@/types/domain';

// Item cost and margin as the server computes them (Item::unitCost, Item::marginPercent).

/** A recipe line as the form holds it, in the request's field names. */
export type RecipeRow = { material_id: string; qty: string };

export const emptyRecipeRow = (): RecipeRow => ({ material_id: '', qty: '' });

/** One line at the ingredient's average cost; 0 until both are filled in. */
export function recipeLineCost(
    row: RecipeRow,
    materials: Map<string, RecipeMaterial>,
): number {
    const qty = parseDecimal(row.qty);
    const material = materials.get(row.material_id);

    return material && qty > 0 ? qty * material.averageCost : 0;
}

/** HPP of one portion, or null while no line has a cost yet. */
export function recipeCost(
    rows: RecipeRow[],
    materials: Map<string, RecipeMaterial>,
): number | null {
    const costs = rows.map((row) => recipeLineCost(row, materials));

    return costs.some((cost) => cost > 0)
        ? roundHalfAway(costs.reduce((sum, cost) => sum + cost, 0))
        : null;
}

/** Margin in rupiah and as a percentage of the price (one decimal), null until both are known. */
export function margin(
    price: number | null,
    unitCost: number | null,
): { rupiah: number; percent: number | null } | null {
    if (price === null || unitCost === null) {
        return null;
    }

    const rupiah = price - unitCost;

    return {
        rupiah,
        percent: price ? roundHalfAway((rupiah / price) * 100, 1) : null,
    };
}

/** What opening stock adds to the books: quantity times purchase price. */
export function openingValue(
    qty: number,
    unitCost: number | null,
): number | null {
    return Number.isFinite(qty) && qty > 0 && unitCost !== null
        ? roundHalfAway(qty * unitCost)
        : null;
}
