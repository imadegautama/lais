<?php

namespace App\Services\Ai;

use App\Enums\ContactType;
use App\Enums\MoneyAccountKind;
use App\Enums\PaymentMethod;
use App\Enums\TransactionKind;
use App\Enums\TransactionType;
use App\Models\Contact;
use App\Models\Item;
use App\Models\MoneyAccount;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The record form, filled from the model's isi_transaksi arguments. Values
 * follow RecordFormData (as TransactionFormResource sends them), laid over
 * what the form already holds; payload() is what the Simpan button would
 * send (record.ts toPayload), so TransactionRequest can judge the draft.
 */
final class TransactionDraft
{
    /** Listed while a non-cash draft has more than one rekening to go to; answered by naming one. */
    public const PICK_ACCOUNT = 'Pilih rekeningnya.';

    /** Types the assistant fills; transfers and opening balances stay by hand. */
    public const TYPES = [
        TransactionType::Penjualan,
        TransactionType::Pembelian,
        TransactionType::Biaya,
        TransactionType::Modal,
        TransactionType::UtangPiutang,
    ];

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $current  the form as the page holds it
     * @param  Collection<int, Item>  $items
     * @param  Collection<int, Contact>  $contacts
     * @param  Collection<int, MoneyAccount>  $moneyAccounts
     * @return array{values: array<string, mixed>, notes: list<string>}
     */
    public static function fill(array $args, array $current, Collection $items, Collection $contacts, Collection $moneyAccounts): array
    {
        $notes = [];
        $values = self::start($current);
        $named = TransactionType::tryFrom(self::text($args['jenis'] ?? null));

        // Another type is another form: start it fresh rather than carry fields it does not have.
        if ($named !== null && in_array($named, self::TYPES, true) && $named->value !== $values['type']) {
            $values = self::start(['type' => $named->value]);
        }

        $type = TransactionType::from($values['type']);
        $kind = TransactionKind::tryFrom(self::text($args['jenis_biaya'] ?? null));

        if ($kind !== null && $kind->type() === $type) {
            $values['kind'] = $kind->value;
        }

        $method = PaymentMethod::tryFrom(self::text($args['metode_bayar'] ?? null));

        if ($method !== null && in_array($method, $type->methods(), true)) {
            $values['method'] = $method->value;
        }

        if ($values['method'] !== PaymentMethod::NonTunai->value) {
            $values['money_account_id'] = '';
        } elseif (self::text($args['rekening'] ?? null) !== '') {
            $account = Names::match($moneyAccounts->where('kind', MoneyAccountKind::NonTunai), $args['rekening'], fn (MoneyAccount $account) => [$account->name]);
            $account === null
                ? $notes[] = 'Rekening "'.self::text($args['rekening']).'" tidak ada di daftar rekening.'
                : $values['money_account_id'] = (string) $account->id;
        }

        // TransactionService picks the rekening only when there is just one.
        if ($values['method'] === PaymentMethod::NonTunai->value && $values['money_account_id'] === '' && $moneyAccounts->where('kind', MoneyAccountKind::NonTunai)->count() !== 1) {
            $notes[] = self::PICK_ACCOUNT;
        }

        if (self::text($args['tanggal'] ?? null) !== '') {
            try {
                $at = CarbonImmutable::parse(self::text($args['tanggal']));
                // A time still to come keeps the form's time; the card shows which one is used.
                if ($at <= now()) {
                    $values['occurred_at'] = $at->format('Y-m-d\TH:i');
                }
            } catch (Throwable) {
                // An unreadable date keeps the form's time.
            }
        }

        if (self::text($args['kontak'] ?? null) !== '') {
            $contactType = ContactType::for($type, TransactionKind::tryFrom((string) $values['kind']));
            $contact = Names::match($contacts->where('type', $contactType), $args['kontak'], fn (Contact $contact) => [$contact->name]);
            $values['contact'] = $contact === null ? self::text($args['kontak']) : (string) $contact->id;
        }

        $listed = fn (Item $item) => [$item->name, ...($item->aliases ?? [])];
        $pool = $items->filter(fn (Item $item) => $type === TransactionType::Penjualan ? $item->kind->isSold() : $item->kind->isStocked());

        if (self::hasLines($type, (string) $values['kind']) && is_array($args['baris'] ?? null) && $args['baris'] !== []) {
            $lines = [];

            foreach ($args['baris'] as $row) {
                // Some models write a line as nama/jumlah, like tulis_nota, whatever the schema says.
                $name = is_array($row) ? ($row['item'] ?? $row['nama'] ?? null) : null;
                $item = Names::match($pool, $name, $listed);
                $written = $row['qty'] ?? $row['jumlah'] ?? null;
                $qty = is_numeric($written) && (float) $written > 0 ? (float) $written : 1;
                // "beras 5 kg 70rb" names the line's total; the unit price is worked out here, not by the model.
                $price = match (true) {
                    is_numeric($row['harga'] ?? null) && (int) $row['harga'] > 0 => (int) $row['harga'],
                    is_numeric($row['total'] ?? null) && (int) $row['total'] > 0 => (int) round((int) $row['total'] / $qty),
                    default => $item === null ? null : self::defaultPrice($item, $type),
                };

                if ($item !== null) {
                    $lines[] = ['item_id' => (string) $item->id, 'qty' => Format::decimalInput($qty), 'price' => $price];
                } elseif (self::text($name) !== '') {
                    // Corrections never remove a waiting line; only the owner's own "catat yang ada saja" does.
                    $values['unknown'] = [
                        ...array_filter($values['unknown'], fn (array $line) => mb_strtolower($line['name']) !== mb_strtolower(self::text($name))),
                        ['name' => mb_substr(self::text($name), 0, 120), 'qty' => Format::decimalInput($qty), 'price' => $price],
                    ];
                }
            }

            if ($lines !== []) {
                $values['lines'] = $lines;
            }
        }

        // Goods not listed yet stay in the draft with their quantity and price. They block saving,
        // so a record never drops a line quietly, and join the lines once the catalog has them.
        $waiting = [];

        foreach (self::hasLines($type, (string) $values['kind']) ? $values['unknown'] : [] as $line) {
            $item = Names::match($pool, $line['name'], $listed);

            if ($item === null) {
                $waiting[] = $line;
                $notes[] = "\"{$line['name']}\" tidak ada di daftar produk.";

                continue;
            }

            $values['lines'] = [
                ...array_filter($values['lines'], fn (array $row) => $row['item_id'] !== ''),
                ['item_id' => (string) $item->id, 'qty' => $line['qty'], 'price' => $line['price'] ?? self::defaultPrice($item, $type)],
            ];
        }

        $values['unknown'] = $waiting;

        if (! self::hasLines($type, (string) $values['kind']) && is_numeric($args['nominal'] ?? null) && (int) $args['nominal'] > 0) {
            $values['amount'] = (int) $args['nominal'];
        }

        if ($type === TransactionType::Penjualan && $values['method'] === PaymentMethod::Kredit->value && is_numeric($args['dibayar'] ?? null)) {
            $values['paid_amount'] = max(0, (int) $args['dibayar']);
        }

        if (self::text($args['catatan'] ?? null) !== '') {
            $values['note'] = mb_substr(self::text($args['catatan']), 0, 200);
        }

        return ['values' => $values, 'notes' => $notes];
    }

    /**
     * What the Simpan button sends for these values (record.ts toPayload).
     *
     * @param  array<string, mixed>  $values
     * @param  Collection<int, Contact>  $contacts
     * @return array<string, mixed>
     */
    public static function payload(array $values, Collection $contacts): array
    {
        $type = TransactionType::from((string) $values['type']);
        $method = (string) $values['method'];
        $contact = self::text($values['contact']);
        $known = $contact !== '' && $contacts->contains(fn (Contact $model) => (string) $model->id === $contact);

        return [
            'type' => $type->value,
            ...($type === TransactionType::Penjualan ? [] : ['kind' => $values['kind']]),
            'method' => $method,
            ...($method === PaymentMethod::NonTunai->value && $values['money_account_id'] !== '' ? ['money_account_id' => (int) $values['money_account_id']] : []),
            'occurred_at' => $values['occurred_at'] ?: null,
            'note' => self::text($values['note']) ?: null,
            ...(self::hasLines($type, (string) $values['kind'])
                ? ['lines' => array_map(fn (array $line) => [
                    'item_id' => $line['item_id'] === '' ? null : (int) $line['item_id'],
                    'qty' => self::decimal($line['qty']),
                    'price' => $line['price'],
                ], $values['lines'])]
                : ['amount' => $values['amount']]),
            ...($type === TransactionType::Penjualan && $method === PaymentMethod::Kredit->value ? ['paid_amount' => $values['paid_amount'] ?? 0] : []),
            ...($known
                ? ['contact_id' => (int) $contact]
                : ($contact !== '' && ! in_array($type, [TransactionType::Modal, TransactionType::UtangPiutang], true) ? ['contact_name' => $contact] : [])),
        ];
    }

    /**
     * What the chat shows before the owner confirms: each line with its
     * subtotal (rounded like TransactionService), the total, and the rest
     * of the record as short notes.
     *
     * @param  array<string, mixed>  $values
     * @param  Collection<int, Item>  $items
     * @param  Collection<int, Contact>  $contacts
     * @param  Collection<int, MoneyAccount>  $moneyAccounts
     * @return array{title: string, lines: list<array{name: string, qty: float|null, unit: string|null, price: int|null, amount: int|null}>, total: array{label: string, amount: int}|null, notes: list<string>}
     */
    public static function card(array $values, Collection $items, Collection $contacts, Collection $moneyAccounts): array
    {
        $type = TransactionType::from((string) $values['type']);
        $kind = TransactionKind::tryFrom((string) $values['kind']);
        $method = PaymentMethod::tryFrom((string) $values['method']);
        $lines = [];
        $total = null;

        if (self::hasLines($type, (string) $values['kind'])) {
            foreach ($values['lines'] as $line) {
                $item = $items->firstWhere('id', (int) $line['item_id']);
                $qty = self::decimal($line['qty']);

                if ($item !== null) {
                    $lines[] = [
                        'name' => $item->name,
                        'qty' => $qty,
                        'unit' => $item->unit,
                        'price' => $line['price'],
                        'amount' => $qty === null || $line['price'] === null ? null : (int) round($qty * $line['price']),
                    ];
                }
            }

            foreach ($values['unknown'] as $line) {
                $qty = self::decimal($line['qty']);
                $lines[] = [
                    'name' => $line['name'],
                    'qty' => $qty,
                    'unit' => null,
                    'price' => $line['price'],
                    'amount' => $qty === null || $line['price'] === null ? null : (int) round($qty * $line['price']),
                ];
            }

            $amounts = array_column($lines, 'amount');
            $total = $lines === [] || in_array(null, $amounts, true) ? null : ['label' => 'Total', 'amount' => array_sum($amounts)];
        } elseif (is_int($values['amount'])) {
            $total = ['label' => 'Nominal', 'amount' => $values['amount']];
        }

        $notes = [];

        if ($method !== null) {
            $nonCash = $moneyAccounts->where('kind', MoneyAccountKind::NonTunai);
            // With one non-tunai rekening the form and the service pick it by themselves.
            $account = $method === PaymentMethod::NonTunai && $values['money_account_id'] === '' && $nonCash->count() === 1
                ? $nonCash->first()
                : $moneyAccounts->firstWhere('id', (int) $values['money_account_id']);
            $notes[] = $method->label().($account === null ? '' : " ke {$account->name}");
        }

        if ($type === TransactionType::Penjualan && $method === PaymentMethod::Kredit && ($values['paid_amount'] ?? 0) > 0) {
            $notes[] = 'Sudah dibayar '.Format::rupiah($values['paid_amount']);
        }

        $contact = $contacts->firstWhere('id', (int) $values['contact']);
        $notes[] = $contact->name ?? self::text($values['contact']);
        $notes[] = self::text($values['note']);
        // The time comes back from the browser; an unreadable one is refused when saving.
        $at = rescue(fn () => CarbonImmutable::parse((string) $values['occurred_at']), fn () => CarbonImmutable::now(), false);
        $notes[] = Format::date($at).', '.$at->format('H.i');

        return [
            'title' => match ($type) {
                TransactionType::Penjualan => 'Penjualan',
                TransactionType::Pembelian => $kind === TransactionKind::Peralatan ? 'Belanja peralatan' : 'Belanja',
                TransactionType::Biaya => trim('Biaya '.mb_strtolower($kind?->label() ?? '')),
                default => $kind?->label() ?? $type->label(),
            },
            'lines' => $lines,
            'total' => $total,
            'notes' => array_values(array_filter($notes, fn (string $note) => $note !== '')),
        ];
    }

    public static function hasLines(TransactionType $type, string $kind): bool
    {
        return $type === TransactionType::Penjualan || ($type === TransactionType::Pembelian && $kind === TransactionKind::Barang->value);
    }

    /**
     * The form's starting values (pages/transactions/create.tsx), keeping only
     * what the page sent that belongs to the form.
     *
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private static function start(array $current): array
    {
        $type = TransactionType::tryFrom(self::text($current['type'] ?? null));
        $type = $type !== null && in_array($type, self::TYPES, true) ? $type : TransactionType::Penjualan;
        $defaults = [
            'type' => $type->value,
            'kind' => $type === TransactionType::Pembelian ? TransactionKind::Barang->value : '',
            'method' => PaymentMethod::Tunai->value,
            'occurred_at' => now()->format('Y-m-d\TH:i'),
            'lines' => [['item_id' => '', 'qty' => '1', 'price' => null]],
            'unknown' => [],
            'amount' => null,
            'paid_amount' => null,
            'contact' => '',
            'money_account_id' => '',
            'to_money_account_id' => '',
            'note' => '',
        ];
        $values = array_merge($defaults, array_intersect_key($current, $defaults), ['type' => $type->value]);

        $lines = array_values(array_filter(is_array($values['lines']) ? $values['lines'] : [], 'is_array'));
        $values['lines'] = $lines === [] ? $defaults['lines'] : array_map(fn (array $line) => [
            'item_id' => self::text($line['item_id'] ?? null),
            'qty' => self::text($line['qty'] ?? null),
            'price' => is_numeric($line['price'] ?? null) ? (int) $line['price'] : null,
        ], $lines);
        $values['unknown'] = array_values(array_map(fn (array $line) => [
            'name' => mb_substr(self::text($line['name'] ?? null), 0, 120),
            'qty' => self::text($line['qty'] ?? null),
            'price' => is_numeric($line['price'] ?? null) ? (int) $line['price'] : null,
        ], array_filter(is_array($values['unknown']) ? $values['unknown'] : [], fn (mixed $line) => is_array($line) && self::text($line['name'] ?? null) !== '')));

        foreach (['kind', 'method', 'occurred_at', 'contact', 'money_account_id', 'to_money_account_id', 'note'] as $key) {
            $values[$key] = self::text($values[$key]);
        }

        foreach (['amount', 'paid_amount'] as $key) {
            $values[$key] = is_numeric($values[$key]) ? (int) $values[$key] : null;
        }

        return $values;
    }

    private static function defaultPrice(Item $item, TransactionType $type): ?int
    {
        if ($type === TransactionType::Penjualan) {
            return $item->price;
        }

        return $item->averageCost() > 0 ? (int) round($item->averageCost()) : null;
    }

    /** A quantity as the owner typed it ("0,5") read as a number (lib/number.ts parseDecimal). */
    private static function decimal(mixed $text): ?float
    {
        $value = str_replace(',', '.', self::text($text));

        return is_numeric($value) ? (float) $value : null;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? trim((string) $value) : '';
    }
}
