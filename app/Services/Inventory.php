<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stock in and out at rupiah value. The Persediaan account always equals the
 * sum of items.stock_value because both change by the same rounded amount.
 */
class Inventory
{
    public function __construct(private Ledger $ledger) {}

    /**
     * Add stock at a unit cost and journal it against $creditAccount
     * (3101 Modal Pemilik for opening stock).
     */
    public function receive(Item $item, float $qty, int $unitCost, string $reason, string $creditAccount): StockMovement
    {
        $this->assertStocked($item);

        if ($qty <= 0 || $unitCost < 0) {
            throw new InvalidArgumentException('Received quantity must be positive and cost non-negative.');
        }

        $value = (int) round($qty * $unitCost);

        return DB::transaction(function () use ($item, $qty, $value, $reason, $creditAccount) {
            $entry = $value > 0
                ? $this->ledger->post($item->business, "Stok masuk: {$item->name}", [
                    ['account' => '1301', 'debit' => $value],
                    ['account' => $creditAccount, 'credit' => $value],
                ], $item)
                : null;

            return $this->move($item, $qty, $value, $reason, $entry?->id);
        });
    }

    /**
     * Set stock to a physical count. The difference is valued at the current
     * average cost and journaled to 6201 Selisih Persediaan. Returns null when
     * the count matches the system.
     */
    public function adjust(Item $item, float $physicalQty): ?StockMovement
    {
        $this->assertStocked($item);

        if ($physicalQty < 0) {
            throw new InvalidArgumentException('Physical stock cannot be negative.');
        }

        $diff = round($physicalQty - (float) $item->stock, 3);

        if ($diff == 0.0) {
            return null;
        }

        // Counting down to zero clears the remaining value exactly, so no rounding residue is left behind.
        $value = $physicalQty == 0.0 ? -$item->stock_value : (int) round($diff * $item->averageCost());

        return DB::transaction(function () use ($item, $diff, $value) {
            $lines = $value < 0
                ? [['account' => '6201', 'debit' => -$value], ['account' => '1301', 'credit' => -$value]]
                : [['account' => '1301', 'debit' => $value], ['account' => '6201', 'credit' => $value]];

            $entry = $value !== 0 ? $this->ledger->post($item->business, "Koreksi stok: {$item->name}", $lines, $item) : null;

            return $this->move($item, $diff, $value, 'koreksi', $entry?->id);
        });
    }

    /**
     * shortcut: reads stock from the given model without a row lock, fine while one owner edits from the web; add lockForUpdate when the WA bot writes in parallel (slice 2).
     */
    private function move(Item $item, float $qty, int $value, string $reason, ?int $journalEntryId): StockMovement
    {
        $item->forceFill([
            'stock' => round((float) $item->stock + $qty, 3),
            'stock_value' => $item->stock_value + $value,
        ])->save();

        return StockMovement::query()->create([
            'business_id' => $item->business_id,
            'item_id' => $item->id,
            'qty' => $qty,
            'value' => $value,
            'reason' => $reason,
            'journal_entry_id' => $journalEntryId,
            'occurred_at' => now(),
        ]);
    }

    private function assertStocked(Item $item): void
    {
        if (! $item->hasStock()) {
            throw new InvalidArgumentException('Recipe products have no stock of their own.');
        }
    }
}
