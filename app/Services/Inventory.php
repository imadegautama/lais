<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockMovement;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stock in and out at rupiah value. The Persediaan account always equals the
 * sum of items.stock_value because both change by the same rounded amount.
 *
 * Value rules, which keep "stock ≤ 0 ⇒ stock_value = 0":
 * - A sale is never blocked. Units sold past zero leave at value 0 (PRD US-03).
 * - Buying while stock ≤ 0 restarts the average at the purchase price (PRD US-02).
 *   The cost of units already sold is returned to the caller to book as HPP.
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

        $cost = (int) round($qty * $unitCost);

        return DB::transaction(function () use ($item, $qty, $cost, $reason, $creditAccount) {
            $this->lock($item);
            $movement = $this->stockIn($item, $qty, $cost, $reason);
            $lines = Ledger::lines(['1301' => $movement->value, '5101' => $cost - $movement->value, $creditAccount => -$cost]);

            if ($lines !== []) {
                $entry = $this->ledger->post($item->business, "Stok masuk: {$item->name}", $lines, $item);
                $movement->update(['journal_entry_id' => $entry->id]);
            }

            return $movement;
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

        return DB::transaction(function () use ($item, $physicalQty) {
            $this->lock($item);
            $diff = round($physicalQty - (float) $item->stock, 3);

            if ($diff == 0.0) {
                return null;
            }

            // Counting down to zero clears the remaining value exactly, so no rounding residue is left behind.
            $value = $physicalQty == 0.0 ? -$item->stock_value : (int) round($diff * $item->averageCost());
            $entry = $value !== 0
                ? $this->ledger->post($item->business, "Koreksi stok: {$item->name}", Ledger::lines(['1301' => $value, '6201' => -$value]), $item)
                : null;

            return $this->move($item, $diff, $value, 'koreksi', $entry?->id);
        });
    }

    /**
     * Stock in for $cost rupiah. The caller must hold the item's row lock (lock())
     * inside the current DB transaction and book the journal. The movement value
     * goes to Persediaan; $cost minus that value is the cost of units already sold (HPP).
     */
    public function stockIn(Item $item, float $qty, int $cost, string $reason, ?int $transactionId = null, ?CarbonInterface $occurredAt = null): StockMovement
    {
        $this->assertStocked($item);
        $stock = (float) $item->stock;
        $after = round($stock + $qty, 3);

        $value = match (true) {
            $stock > 0 => $cost,
            // All of it covers units sold while out of stock.
            $after <= 0 => -$item->stock_value,
            default => (int) round($after * $cost / $qty) - $item->stock_value,
        };

        return $this->move($item, $qty, $value, $reason, null, $transactionId, $occurredAt);
    }

    /**
     * Stock out at the current average cost; the movement value is negative.
     * Same locking contract as stockIn().
     */
    public function stockOut(Item $item, float $qty, string $reason, int $transactionId, ?CarbonInterface $occurredAt = null): StockMovement
    {
        $this->assertStocked($item);
        $stock = (float) $item->stock;
        $after = round($stock - $qty, 3);

        $value = match (true) {
            $stock <= 0 => 0,
            // The last units take whatever value is left, so nothing is stranded.
            $after <= 0 => -$item->stock_value,
            default => -(int) round($qty * $item->averageCost()),
        };

        return $this->move($item, -$qty, $value, $reason, null, $transactionId, $occurredAt);
    }

    /**
     * Re-read stock under a row lock into the given instance, so concurrent
     * writers (web form, WhatsApp bot) cannot overwrite each other.
     */
    public function lock(Item $item): void
    {
        $fresh = Item::query()->withTrashed()->whereKey($item->getKey())->lockForUpdate()->firstOrFail(['id', 'stock', 'stock_value']);

        $item->forceFill(['stock' => $fresh->stock, 'stock_value' => $fresh->stock_value])
            ->syncOriginalAttributes(['stock', 'stock_value']);
    }

    private function move(Item $item, float $qty, int $value, string $reason, ?int $journalEntryId, ?int $transactionId = null, ?CarbonInterface $occurredAt = null): StockMovement
    {
        $after = round((float) $item->stock + $qty, 3);

        $item->forceFill([
            'stock' => $after,
            'stock_value' => $item->stock_value + $value,
        ])->save();

        return StockMovement::query()->create([
            'business_id' => $item->business_id,
            'item_id' => $item->id,
            'qty' => $qty,
            'stock_after' => $after,
            'value' => $value,
            'reason' => $reason,
            'journal_entry_id' => $journalEntryId,
            'transaction_id' => $transactionId,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    private function assertStocked(Item $item): void
    {
        if (! $item->hasStock()) {
            throw new InvalidArgumentException('Recipe products have no stock of their own.');
        }
    }
}
