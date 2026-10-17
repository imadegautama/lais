import { roundHalfAway, roundQty } from '@/lib/number';
import { ItemKind } from '@/types/enums';

// The stock value rules of App\Services\Inventory, so previews match what the
// server books. Keep the two in step; stock.test.ts uses the PHP tests' numbers.

/** Stock on hand and its rupiah value (items.stock_value). */
export type StockLevel = { stock: number; stockValue: number };

export function averageCost({ stock, stockValue }: StockLevel): number {
    return stock > 0 ? stockValue / stock : 0;
}

/**
 * Rupiah that leaves Persediaan when qty is sold (Inventory::stockOut).
 * A sale is never blocked; units sold past zero leave at no value.
 */
export function stockOutCost(level: StockLevel, qty: number): number {
    if (level.stock <= 0) {
        return 0;
    }

    // The last units take whatever value is left, so nothing is stranded.
    return roundQty(level.stock - qty) <= 0
        ? level.stockValue
        : roundHalfAway(qty * averageCost(level));
}

/**
 * Rupiah of a purchase that stays in Persediaan (Inventory::stockIn). Buying
 * while stock is at or below zero restarts the average at the purchase price;
 * the rest of the cost covers units already sold and is HPP.
 */
export function stockInValue(
    level: StockLevel,
    qty: number,
    cost: number,
): number {
    const after = roundQty(level.stock + qty);

    if (level.stock > 0) {
        return cost;
    }

    return after <= 0
        ? -level.stockValue
        : roundHalfAway((after * cost) / qty) - level.stockValue;
}

export function afterStockOut(level: StockLevel, qty: number): StockLevel {
    return {
        stock: roundQty(level.stock - qty),
        stockValue: level.stockValue - stockOutCost(level, qty),
    };
}

export function afterStockIn(
    level: StockLevel,
    qty: number,
    cost: number,
): StockLevel {
    return {
        stock: roundQty(level.stock + qty),
        stockValue: level.stockValue + stockInValue(level, qty, cost),
    };
}

/** Value of a count correction (Inventory::adjust); counting to zero clears it exactly. */
export function adjustmentValue(level: StockLevel, physical: number): number {
    const diff = roundQty(physical - level.stock);

    if (diff === 0) {
        return 0;
    }

    return physical === 0
        ? -level.stockValue
        : roundHalfAway(diff * averageCost(level));
}

/**
 * What leaves the shelf when qty of an item is sold: the item itself, or each
 * recipe ingredient times qty (TransactionService::stockedParts).
 */
export function stockedParts(
    item: {
        id: number;
        kind: ItemKind;
        recipe: Array<{ materialId: number; qty: number }>;
    },
    qty: number,
): Array<{ id: number; qty: number }> {
    return item.kind === ItemKind.Komposisi
        ? item.recipe.map((line) => ({
              id: line.materialId,
              qty: roundQty(line.qty * qty),
          }))
        : [{ id: item.id, qty }];
}
