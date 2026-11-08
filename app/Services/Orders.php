<?php

namespace App\Services;

use App\Enums\ContactType;
use App\Enums\ItemKind;
use App\Enums\OrderFulfillment;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TransactionKind;
use App\Enums\TransactionSource;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Transaction;
use App\Support\Format;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pesanan (PRD M4): from the shop page or the owner, through to the sale.
 * Money never sits on the order (§4.4): before it is done every payment is
 * uang muka (Kas / 2201), the sale settles that when done and leaves the
 * rest as piutang, and later payments are Terima piutang. Prices always
 * come from the catalog, never from the browser.
 */
class Orders
{
    public function __construct(private TransactionService $transactions, private Debts $debts) {}

    /**
     * "0812…", "+62812…", "62 812-…" → "62812…", the form wa.me expects.
     */
    public static function phone(string $phone): string
    {
        $digits = (string) preg_replace('/\D/', '', $phone);

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }

    /**
     * Can a customer order it now: sold, not archived, and in stock (a recipe
     * product follows the owner's "tersedia" switch instead).
     */
    public static function orderable(Item $item): bool
    {
        return $item->kind->isSold() && ! $item->trashed()
            && ($item->kind === ItemKind::Komposisi ? $item->available : (float) $item->stock > 0);
    }

    /**
     * Units open orders hold per item, which the shop may not sell again.
     *
     * @param  array<int, int>|null  $itemIds
     * @return array<int, float> item id => units held
     */
    public static function held(Business $business, ?array $itemIds = null): array
    {
        return OrderLine::query()
            ->join('orders', 'orders.id', '=', 'order_lines.order_id')
            ->where('orders.business_id', $business->id)
            ->whereIn('orders.status', OrderStatus::holdsStock())
            ->when($itemIds !== null, fn ($query) => $query->whereIn('order_lines.item_id', $itemIds))
            ->groupBy('order_lines.item_id')
            ->selectRaw('order_lines.item_id, SUM(order_lines.qty) AS held')
            ->pluck('held', 'item_id')
            ->map(fn ($held) => (float) $held)
            ->all();
    }

    /**
     * How many more the shop may take: stock less what open orders hold.
     * Null for a recipe product, which follows its "tersedia" switch instead.
     */
    public static function left(Item $item, float $held): ?int
    {
        return $item->kind === ItemKind::Komposisi ? null : max(0, (int) floor(round((float) $item->stock - $held, 3)));
    }

    /**
     * A new order. From the shop it waits for the owner; one the owner enters
     * (taken by phone or chat) is accepted at once.
     *
     * @param  array<string, mixed>  $data  validated OrderRequest data
     */
    public function place(Business $business, array $data, TransactionSource $source): Order
    {
        $fromShop = $source === TransactionSource::Toko;

        if ($fromShop && ! $business->accepting_orders) {
            throw ValidationException::withMessages(['lines' => 'Toko sedang tidak menerima pesanan.']);
        }

        return DB::transaction(function () use ($business, $data, $source, $fromShop) {
            /** @var list<array{item_id: int|string, qty: int|string}> $requested */
            $requested = $data['lines'];
            $wanted = collect($requested)->mapWithKeys(fn (array $line) => [(int) $line['item_id'] => (int) $line['qty']]);
            // Locked so two customers cannot both take the last units.
            $items = $business->items()->whereKey($wanted->keys())->lockForUpdate()->get()->keyBy('id');
            // The owner may take more than the stock (made or bought later); the shop may not.
            $held = $fromShop ? self::held($business, $wanted->keys()->all()) : [];
            $lines = [];

            foreach ($wanted as $id => $qty) {
                $item = $items->get($id);

                if ($item === null || ($fromShop && ! $item->show_in_store) || ! self::orderable($item) || $item->price === null) {
                    throw ValidationException::withMessages(['lines' => ($item === null ? 'Ada produk yang' : $item->name).' sedang tidak bisa dipesan. Hapus dari pesanan, lalu kirim lagi.']);
                }

                $left = $fromShop ? self::left($item, $held[$id] ?? 0.0) : null;

                if ($left !== null && $qty > $left) {
                    throw ValidationException::withMessages(['lines' => $left === 0
                        ? "{$item->name} sudah habis dipesan. Hapus dari pesanan, lalu kirim lagi."
                        : "Stok {$item->name} tinggal {$left} {$item->unit}. Kurangi jumlahnya, lalu kirim lagi."]);
                }

                $lines[] = ['item_id' => $item->id, 'name' => $item->name, 'unit' => $item->unit, 'qty' => $qty, 'price' => $item->price, 'subtotal' => $qty * $item->price];
            }

            $phone = self::phone($data['customer_phone']);
            $order = $business->orders()->create([
                'code' => $this->nextCode($business),
                'token' => Str::random(32),
                'status' => $fromShop ? OrderStatus::Menunggu : OrderStatus::Diproses,
                'source' => $source,
                'customer_name' => trim($data['customer_name']),
                'customer_phone' => $phone,
                'fulfillment' => OrderFulfillment::from($data['fulfillment']),
                'address' => ($data['fulfillment'] === OrderFulfillment::Antar->value ? trim((string) ($data['address'] ?? '')) : '') ?: null,
                'wanted_at' => $data['wanted_at'] ?? null,
                'note' => trim((string) ($data['note'] ?? '')) ?: null,
                'total' => array_sum(array_column($lines, 'subtotal')),
                'accepted_at' => $fromShop ? null : now(),
                'contact_id' => $fromShop ? null : $this->customer($business, trim($data['customer_name']), $phone)->id,
            ]);
            $order->lines()->createMany($lines);

            return $order;
        });
    }

    /**
     * "Terima": the customer becomes a contact, matched by WA number.
     */
    public function accept(Order $order): Order
    {
        $this->assert($order->status->canAccept(), 'Pesanan ini sudah diterima atau ditolak.');

        $order->update([
            'status' => OrderStatus::Diproses,
            'accepted_at' => now(),
            'contact_id' => $this->customer($order->business, $order->customer_name, $order->customer_phone)->id,
        ]);

        return $order;
    }

    public function reject(Order $order, ?string $reason): Order
    {
        $this->assert($order->status->canReject(), 'Hanya pesanan yang masih menunggu yang bisa ditolak. Pesanan yang sudah diterima dibatalkan.');

        $order->update(['status' => OrderStatus::Ditolak, 'rejected_at' => now(), 'reject_reason' => $reason ?: null]);

        return $order;
    }

    public function markReady(Order $order): Order
    {
        $this->assert($order->status->canMarkReady(), 'Pesanan ini belum diterima atau sudah lewat tahap siap.');

        $order->update(['status' => OrderStatus::Siap, 'ready_at' => now()]);

        return $order;
    }

    /**
     * An accepted order called off: its uang muka is returned, by cancelling
     * those records (mirror journal), and it ends as Ditolak.
     */
    public function cancel(Order $order, ?string $reason): Order
    {
        $this->assert($order->status->canCancel(), 'Pesanan ini tidak bisa dibatalkan lagi.');

        return DB::transaction(function () use ($order, $reason) {
            foreach ($this->postedPayments($order) as $deposit) {
                $this->transactions->cancel($deposit, "Pesanan {$order->code} dibatalkan");
            }

            $order->update(['status' => OrderStatus::Ditolak, 'rejected_at' => now(), 'reject_reason' => $reason ?: null]);

            return $order;
        });
    }

    /**
     * "Selesai": the sale is recognized now, stock goes out, the uang muka
     * pays for it, and whatever is still unpaid becomes piutang.
     */
    public function complete(Order $order): Order
    {
        $this->assert($order->status->canComplete(), 'Pesanan ini belum diterima atau sudah selesai.');

        return DB::transaction(function () use ($order) {
            $deposit = $this->paid($order);
            $sale = $this->transactions->record($order->business, [
                'type' => TransactionType::Penjualan->value,
                // Paid in full ahead: a cash sale with no new money; otherwise the rest is owed.
                'method' => ($deposit >= $order->total ? PaymentMethod::Tunai : PaymentMethod::Kredit)->value,
                'lines' => $order->lines->map(fn ($line) => ['item_id' => $line->item_id, 'qty' => $line->qty, 'price' => $line->price])->all(),
                'contact_id' => $order->contact_id,
                'deposit' => $deposit,
                'note' => "Pesanan {$order->code}",
                'order_id' => $order->id,
            ], $order->source);

            $order->update(['status' => OrderStatus::Selesai, 'completed_at' => now(), 'transaction_id' => $sale->id]);

            return $order;
        });
    }

    /**
     * Money the customer handed over: uang muka before the order is done,
     * Terima piutang after, never more than what is still unpaid.
     */
    public function recordPayment(Order $order, int $amount, PaymentMethod $method, ?int $moneyAccountId = null): Transaction
    {
        $this->assert($order->status->acceptsPayment(), 'Pembayaran baru bisa dicatat setelah pesanan diterima.');
        $rest = $order->total - $this->paid($order);

        if ($amount > $rest) {
            throw ValidationException::withMessages(['amount' => $rest > 0 ? 'Sisa yang belum dibayar '.Format::rupiah($rest).'. Nominal tidak boleh lebih dari itu.' : 'Pesanan ini sudah lunas.']);
        }

        return $this->transactions->record($order->business, [
            'type' => TransactionType::UtangPiutang->value,
            'kind' => ($order->status === OrderStatus::Selesai ? TransactionKind::TerimaPiutang : TransactionKind::UangMuka)->value,
            'method' => $method->value,
            'money_account_id' => $moneyAccountId,
            'amount' => $amount,
            'contact_id' => $order->contact_id,
            'note' => "Pesanan {$order->code}",
            'order_id' => $order->id,
        ]);
    }

    /**
     * What the customer paid so far: uang muka, and once done, whatever of the
     * sale's piutang is settled, from this order or the debts page (Debts).
     */
    public function paid(Order $order): int
    {
        $deposits = (int) $order->transactions()
            ->where('transactions.status', TransactionStatus::Posted)
            ->where('transactions.kind', TransactionKind::UangMuka)
            ->sum('transactions.total');
        $sale = $order->sale;

        // A sale paid in full by uang muka is a cash sale and raises no piutang.
        if ($sale === null || $sale->method !== PaymentMethod::Kredit) {
            return $deposits;
        }

        return $deposits + ($sale->total - $sale->paid_amount) - $this->debts->remaining($sale);
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function postedPayments(Order $order)
    {
        return $order->transactions()
            ->where('transactions.status', TransactionStatus::Posted)
            ->whereIn('transactions.kind', [TransactionKind::UangMuka, TransactionKind::TerimaPiutang])
            ->orderBy('transactions.occurred_at')
            ->get();
    }

    /**
     * The customer as a contact: the one with this WA number, else a new one.
     * A namesake with another number gets the number's last digits, so two
     * people never share one tab.
     */
    private function customer(Business $business, string $name, string $phone): Contact
    {
        $contacts = $business->contacts()->where('contacts.type', ContactType::Pelanggan);
        $known = (clone $contacts)->where('contacts.phone', $phone)->first();

        if ($known !== null) {
            return $known;
        }

        $namesake = (clone $contacts)->where('contacts.name', $name)->first();

        if ($namesake !== null && $namesake->phone === null) {
            $namesake->update(['phone' => $phone]);

            return $namesake;
        }

        return $business->contacts()->create([
            'type' => ContactType::Pelanggan,
            'name' => $namesake === null ? $name : mb_substr($name, 0, 110).' · '.substr($phone, -4),
            'phone' => $phone,
        ]);
    }

    /**
     * A001, A002, … per business. The last code is read under a lock so two
     * orders at once never share one.
     */
    private function nextCode(Business $business): string
    {
        $last = $business->orders()->lockForUpdate()->orderByDesc('orders.id')->value('orders.code');

        return 'A'.str_pad((string) ((int) substr((string) $last, 1) + 1), 3, '0', STR_PAD_LEFT);
    }

    private function assert(bool $allowed, string $message): void
    {
        if (! $allowed) {
            throw ValidationException::withMessages(['order' => $message]);
        }
    }
}
