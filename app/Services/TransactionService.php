<?php

namespace App\Services;

use App\Enums\ActivityCategory;
use App\Enums\ActivityEvent;
use App\Enums\ContactType;
use App\Enums\PaymentMethod;
use App\Enums\StockReason;
use App\Enums\TransactionKind;
use App\Enums\TransactionSource;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Item;
use App\Models\Transaction;
use App\Support\Format;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

/**
 * Records the five transaction types of PRD M2. Every transaction posts one
 * balanced journal entry (PRD §4.4) and its stock movements in one DB
 * transaction. The web form and, later, the WhatsApp bot both come through here,
 * and so do cancelling and editing (cancel(), replace()).
 */
class TransactionService
{
    public function __construct(private Ledger $ledger, private Inventory $inventory) {}

    /**
     * @param  array<string, mixed>  $data  validated TransactionRequest data
     */
    public function record(Business $business, array $data, TransactionSource $source = TransactionSource::Web): Transaction
    {
        return DB::transaction(function () use ($business, $data, $source) {
            $transaction = $this->write($business, $data, $source);
            $this->log($transaction, ActivityEvent::Created, ['total' => $transaction->total, 'source' => $source->value]);

            return $transaction;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function write(Business $business, array $data, TransactionSource $source): Transaction
    {
        $type = TransactionType::from((string) $data['type']);
        $kind = isset($data['kind']) ? TransactionKind::from((string) $data['kind']) : null;
        $method = PaymentMethod::from((string) $data['method']);

        if (! in_array($method, $type->methods(), true) || ($kind !== null && $kind->type() !== $type)) {
            throw new InvalidArgumentException("{$method->value} / {$kind?->value} is not allowed for {$type->value}.");
        }

        return DB::transaction(function () use ($business, $data, $type, $kind, $method, $source) {
            $occurredAt = isset($data['occurred_at']) ? Carbon::parse((string) $data['occurred_at']) : now();
            $contact = $this->contact($business, $data, $type, $kind);
            /** @var list<array{item_id: int, qty: float|int|string, price: int}> $lines */
            $lines = array_values($data['lines'] ?? []);
            $items = $this->lockItems($business, $lines);

            $total = $lines === []
                ? (int) $data['amount']
                : array_sum(array_map(fn (array $line) => (int) round((float) $line['qty'] * $line['price']), $lines));
            $paid = $type === TransactionType::Penjualan && $method === PaymentMethod::Kredit ? (int) ($data['paid_amount'] ?? 0) : 0;

            $this->assertRules($business, $type, $kind, $method, $total, $paid, $contact);

            $amounts = $this->moneyLines($type, $kind, $method, $total, $paid);

            $transaction = $business->transactions()->create([
                'type' => $type,
                'kind' => $kind,
                'method' => $method,
                'occurred_at' => $occurredAt,
                'total' => $total,
                'paid_amount' => $paid,
                'cash_flow' => ($amounts[Account::CASH] ?? 0) + ($amounts[Account::NON_CASH] ?? 0),
                'description' => $this->describe($type, $kind, $lines, $items, $contact, $data['note'] ?? null),
                'note' => $data['note'] ?? null,
                'contact_id' => $contact?->id,
                'source' => $source,
            ]);

            foreach ($lines as $line) {
                $item = $items[(int) $line['item_id']];
                $qty = (float) $line['qty'];
                $subtotal = (int) round($qty * $line['price']);
                $hpp = null;

                if ($type === TransactionType::Penjualan) {
                    $hpp = 0;

                    foreach ($this->stockedParts($item, $qty, $items) as [$part, $partQty]) {
                        $hpp -= $this->inventory->stockOut($part, $partQty, StockReason::Penjualan, $transaction->id, $occurredAt)->value;
                    }

                    $amounts = self::add($amounts, [Account::COGS => $hpp, Account::INVENTORY => -$hpp]);
                } else {
                    $value = $this->inventory->stockIn($item, $qty, $subtotal, StockReason::Pembelian, $transaction->id, $occurredAt)->value;
                    // Whatever does not stay in Persediaan covered units sold while out of stock.
                    $amounts = self::add($amounts, [Account::INVENTORY => $value, Account::COGS => $subtotal - $value]);
                }

                $transaction->lines()->create([
                    'item_id' => $item->id,
                    'qty' => $qty,
                    'price' => $line['price'],
                    'subtotal' => $subtotal,
                    'hpp' => $hpp,
                ]);
            }

            $this->ledger->post($business, $transaction->description, Ledger::lines($amounts), $transaction, $occurredAt);

            return $transaction;
        });
    }

    /**
     * "Batalkan" (PRD §4): the record stays, marked void, with a mirror journal
     * and reversed stock movements. Nothing is ever deleted.
     */
    public function cancel(Transaction $transaction, ?string $reason = null): Transaction
    {
        return DB::transaction(function () use ($transaction, $reason) {
            $this->void($transaction, $reason);
            $this->assertNotOverpaid($transaction);
            $this->log($transaction, ActivityEvent::Cancelled, ['reason' => $reason]);

            return $transaction;
        });
    }

    /**
     * "Ubah": cancel and record again in one step, so leaving the form or a
     * validation error never leaves the old record cancelled on its own.
     *
     * @param  array<string, mixed>  $data  validated TransactionRequest data
     */
    public function replace(Transaction $transaction, array $data): Transaction
    {
        return DB::transaction(function () use ($transaction, $data) {
            $this->void($transaction, null);
            $replacement = $this->write($transaction->business, $data, TransactionSource::Web);
            $transaction->forceFill(['replaced_by_id' => $replacement->id])->save();
            // Checked after recording again: a corrected total can still cover what was paid.
            $this->assertNotOverpaid($transaction);

            $this->log($transaction, ActivityEvent::Replaced, ['replacement_id' => $replacement->id], self::differences($transaction, $replacement));
            $this->log($replacement, ActivityEvent::Created, ['total' => $replacement->total, 'replaces_id' => $transaction->id]);

            return $replacement;
        });
    }

    /**
     * The journal is mirrored on the original date, so that period nets to
     * zero like the transaction list, which leaves void records out.
     */
    private function void(Transaction $transaction, ?string $reason): void
    {
        if (Transaction::query()->whereKey($transaction->id)->lockForUpdate()->value('status') !== TransactionStatus::Posted) {
            throw ValidationException::withMessages(['transaction' => 'Catatan ini sudah dibatalkan.']);
        }

        $movements = $transaction->stockMovements()->orderBy('stock_movements.id')->get();
        $items = Item::query()->withTrashed()->whereKey($movements->pluck('item_id')->unique()->sort()->values())
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $archived = $items->filter(fn (Item $item) => $item->trashed());

        if ($archived->isNotEmpty()) {
            throw ValidationException::withMessages(['transaction' => "{$archived->pluck('name')->join(', ', ' dan ')} sudah diarsipkan, jadi stoknya tidak bisa dikembalikan."]);
        }

        $amounts = [];

        foreach ($transaction->journalEntries()->with('lines.account')->get() as $entry) {
            foreach ($entry->lines as $line) {
                $amounts = self::add($amounts, [$line->account->code => $line->credit - $line->debit]);
            }
        }

        foreach ($movements as $movement) {
            $reversed = $this->inventory->reverse($items[$movement->item_id], $movement, $transaction->id);
            // Zero when the stock went back exactly; otherwise the gap is HPP, as in Inventory's rules.
            $gap = $reversed->value + $movement->value;
            $amounts = self::add($amounts, [Account::INVENTORY => $gap, Account::COGS => -$gap]);
        }

        $this->ledger->post($transaction->business, mb_strimwidth("Pembatalan: {$transaction->description}", 0, 255, '…'), Ledger::lines($amounts), $transaction, $transaction->occurred_at);

        $transaction->forceFill(['status' => TransactionStatus::Void, 'voided_at' => now(), 'void_reason' => $reason])->save();
    }

    /**
     * A credit record cannot go while payments against it remain: the contact
     * would end up owed money back.
     */
    private function assertNotOverpaid(Transaction $transaction): void
    {
        if ($transaction->contact_id === null) {
            return;
        }

        $balance = $this->ledger->contactBalances($transaction->business, $transaction->contact_id)[$transaction->contact_id] ?? 0;

        if ($balance < 0) {
            $contact = $transaction->contact;

            throw ValidationException::withMessages(['transaction' => $contact->type === ContactType::Pelanggan
                ? "{$contact->name} sudah membayar sebagian tagihan ini, jadi sisa tagihannya akan minus ".Format::rupiah(-$balance).'. Batalkan pembayarannya dulu.'
                : "Pembayaran ke {$contact->name} sudah tercatat, jadi sisa utangnya akan minus ".Format::rupiah(-$balance).'. Batalkan pembayarannya dulu.']);
        }
    }

    /**
     * Audit log entry for a transaction (Log aktivitas). The journal already
     * records the money; this records who did it and through which channel.
     *
     * @param  array<string, mixed>  $properties
     * @param  array{old?: array<string, mixed>, attributes?: array<string, mixed>}  $changes
     */
    private function log(Transaction $transaction, ActivityEvent $event, array $properties = [], array $changes = []): void
    {
        $verb = match (true) {
            $event !== ActivityEvent::Created => $event->verb(),
            isset($properties['replaces_id']) => 'Mencatat ulang',
            default => 'Mencatat',
        };
        $logger = activity(ActivityCategory::Transaksi)
            ->event($event->value)
            ->performedOn($transaction)
            ->withProperties(array_filter($properties, fn ($value) => $value !== null));

        if ($changes !== []) {
            $logger->withChanges($changes);
        }

        $logger->log("{$verb} ".mb_strtolower($transaction->type->label()).": {$transaction->description}");
    }

    /**
     * What "Ubah" changed, old and new side by side, for the audit log.
     *
     * @return array{old?: array<string, mixed>, attributes?: array<string, mixed>}
     */
    private static function differences(Transaction $old, Transaction $new): array
    {
        $fields = fn (Transaction $transaction) => [
            'total' => $transaction->total,
            'description' => $transaction->description,
            'method' => $transaction->method->value,
            'contact' => $transaction->contact?->name,
            'occurred_at' => $transaction->occurred_at->toIso8601String(),
        ];
        [$before, $after] = [$fields($old), $fields($new)];
        $changed = array_keys(array_filter($after, fn ($value, $key) => $value !== $before[$key], ARRAY_FILTER_USE_BOTH));

        return $changed === [] ? [] : [
            'old' => array_intersect_key($before, array_flip($changed)),
            'attributes' => array_intersect_key($after, array_flip($changed)),
        ];
    }

    /**
     * The money side of the journal (PRD §4.4); stock lines are added per line.
     *
     * @return array<int|string, int>
     */
    private function moneyLines(TransactionType $type, ?TransactionKind $kind, PaymentMethod $method, int $total, int $paid): array
    {
        $cash = (string) $method->cashAccount();
        $payable = $method === PaymentMethod::Kredit ? Account::PAYABLE : $cash;

        return match ($type) {
            TransactionType::Penjualan => $method === PaymentMethod::Kredit
                // shortcut: the part paid on the spot of a credit sale is cash, add a method for it when orders/DP land.
                ? self::add([Account::CASH => $paid, Account::RECEIVABLE => $total - $paid], [Account::SALES => -$total])
                : self::add([$cash => $total], [Account::SALES => -$total]),
            TransactionType::Pembelian => $kind === TransactionKind::Peralatan
                ? self::add([Account::EQUIPMENT => $total], [$payable => -$total])
                // Goods: Persediaan and any catch-up HPP are added per line.
                : [$payable => -$total],
            TransactionType::Biaya => self::add([Account::EXPENSES => $total], [$payable => -$total]),
            TransactionType::UtangPiutang => $kind === TransactionKind::TerimaPiutang
                ? self::add([$cash => $total], [Account::RECEIVABLE => -$total])
                : self::add([Account::PAYABLE => $total], [$cash => -$total]),
            TransactionType::Modal => $kind === TransactionKind::Setor
                ? self::add([$cash => $total], [Account::OWNER_CAPITAL => -$total])
                : self::add([Account::DRAWINGS => $total], [$cash => -$total]),
        };
    }

    /**
     * Rules the form cannot check alone, raised as validation errors so the web
     * form and the WhatsApp bot get the same messages.
     */
    private function assertRules(Business $business, TransactionType $type, ?TransactionKind $kind, PaymentMethod $method, int $total, int $paid, ?Contact $contact): void
    {
        if ($total < 1) {
            throw ValidationException::withMessages(['lines' => 'Totalnya masih Rp0. Isi jumlah dan harganya.']);
        }

        if (($method === PaymentMethod::Kredit || $type === TransactionType::UtangPiutang) && $contact === null) {
            throw ValidationException::withMessages(['contact_name' => ContactType::for($type, $kind) === ContactType::Pelanggan
                ? 'Pilih atau tambahkan nama pelanggannya.'
                : 'Pilih atau tambahkan nama suppliernya.']);
        }

        if ($type === TransactionType::Penjualan && $method === PaymentMethod::Kredit && $paid >= $total) {
            throw ValidationException::withMessages(['paid_amount' => 'Kalau sudah dibayar penuh, pilih Tunai atau Non-tunai.']);
        }

        if ($type === TransactionType::UtangPiutang && $contact !== null) {
            $balance = $this->ledger->contactBalances($business, $contact->id)[$contact->id] ?? 0;

            if ($balance <= 0) {
                throw ValidationException::withMessages(['contact_id' => "{$contact->name} tidak punya tagihan yang belum dibayar."]);
            }

            if ($total > $balance) {
                throw ValidationException::withMessages(['amount' => "Sisa tagihan {$contact->name} ".Format::rupiah($balance).'. Nominal tidak boleh lebih dari itu.']);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function contact(Business $business, array $data, TransactionType $type, ?TransactionKind $kind): ?Contact
    {
        if (! empty($data['contact_id'])) {
            return $business->contacts()->whereKey($data['contact_id'])->lockForUpdate()->firstOrFail();
        }

        $name = trim((string) ($data['contact_name'] ?? ''));

        if ($name === '') {
            return null;
        }

        return $business->contacts()->firstOrCreate([
            'type' => ContactType::for($type, $kind),
            'name' => $name,
        ]);
    }

    /**
     * Line items keyed by id, with every stocked item they touch (recipe
     * ingredients included) locked in id order to avoid deadlocks.
     *
     * @param  list<array{item_id: int, qty: float|int|string, price: int}>  $lines
     * @return Collection<int, Item>
     */
    private function lockItems(Business $business, array $lines): Collection
    {
        if ($lines === []) {
            return collect();
        }

        $lineItems = $business->items()->with('recipeLines')->whereKey(array_column($lines, 'item_id'))->get();
        $stockedIds = $lineItems
            ->flatMap(fn (Item $item) => $item->hasStock() ? [$item->id] : $item->recipeLines->pluck('material_id')->all())
            ->unique()
            ->sort()
            ->values();

        $locked = Item::query()->withTrashed()->whereKey($stockedIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        // Recipe products have no stock row to lock; they ride along unlocked for their recipe.
        return $lineItems->keyBy('id')->map(fn (Item $item) => $locked->get($item->id, $item))->union($locked);
    }

    /**
     * What leaves the shelf when $qty of $item is sold: the item itself, or
     * each recipe ingredient times the quantity.
     *
     * @param  Collection<int, Item>  $items
     * @return list<array{0: Item, 1: float}>
     */
    private function stockedParts(Item $item, float $qty, Collection $items): array
    {
        if ($item->hasStock()) {
            return [[$item, $qty]];
        }

        return array_values($item->recipeLines
            ->map(fn ($recipeLine) => [
                $items->get($recipeLine->material_id) ?? throw new LogicException("Ingredient {$recipeLine->material_id} of {$item->name} was not loaded."),
                round((float) $recipeLine->qty * $qty, 3),
            ])
            ->all());
    }

    /**
     * "3 Nasi Goreng, 2 Es Teh", "Listrik & air · token Oktober", "Terima bayaran dari Bu Sari".
     *
     * @param  list<array{item_id: int, qty: float|int|string, price: int}>  $lines
     * @param  Collection<int, Item>  $items
     */
    private function describe(TransactionType $type, ?TransactionKind $kind, array $lines, Collection $items, ?Contact $contact, mixed $note): string
    {
        $note = is_string($note) && trim($note) !== '' ? trim($note) : null;

        $text = match (true) {
            $lines !== [] => self::listLines($type, $lines, $items),
            $kind === TransactionKind::Peralatan => 'Peralatan: '.($note ?? 'tanpa nama'),
            $kind === TransactionKind::TerimaPiutang => 'Terima bayaran dari '.($contact->name ?? 'pelanggan'),
            $kind === TransactionKind::BayarUtang => 'Bayar utang ke '.($contact->name ?? 'supplier'),
            // Expense kinds, setor, and prive read as their label.
            default => $kind?->label() ?? $type->label(),
        };

        if ($note !== null && $type !== TransactionType::Pembelian) {
            $text .= " · {$note}";
        }

        return mb_strimwidth($text, 0, 255, '…');
    }

    /**
     * @param  list<array{item_id: int, qty: float|int|string, price: int}>  $lines
     * @param  Collection<int, Item>  $items
     */
    private static function listLines(TransactionType $type, array $lines, Collection $items): string
    {
        $parts = array_map(function (array $line) use ($type, $items) {
            $item = $items[(int) $line['item_id']];
            $qty = Format::qty($line['qty']);

            // Purchases read better with the unit ("30 butir Telur"); sales follow how owners talk ("3 Nasi Goreng").
            return $type === TransactionType::Penjualan ? "{$qty} {$item->name}" : "{$qty} {$item->unit} {$item->name}";
        }, $lines);

        return count($parts) > 3
            ? implode(', ', array_slice($parts, 0, 3)).', dan '.(count($parts) - 3).' lainnya'
            : implode(', ', $parts);
    }

    /**
     * Sum signed amounts per account.
     *
     * @param  array<int|string, int>  $amounts
     * @param  array<int|string, int>  $more
     * @return array<int|string, int>
     */
    private static function add(array $amounts, array $more): array
    {
        foreach ($more as $account => $amount) {
            $amounts[$account] = ($amounts[$account] ?? 0) + $amount;
        }

        return $amounts;
    }
}
