<?php

namespace App\Http\Resources;

use App\Enums\ActivityCategory;
use App\Enums\ActivityEvent;
use App\Enums\ContactType;
use App\Enums\ItemKind;
use App\Enums\PaymentMethod;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Format;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * One audit-log entry, for the Log aktivitas page and each record's Riwayat.
 * Changes come as readable old → new lines with the labels the app uses
 * elsewhere. Load causer and subject.
 *
 * @mixin Activity
 */
class ActivityResource extends JsonResource
{
    /**
     * Label and format of each field that may show as a change, per subject.
     * Fields not listed here are never shown.
     *
     * @var array<class-string, array<string, array{0: string, 1: string}>>
     */
    private const FIELDS = [
        Item::class => [
            'name' => ['Nama', 'text'],
            'kind' => ['Jenis', ItemKind::class],
            'unit' => ['Satuan', 'text'],
            'price' => ['Harga jual', 'rupiah'],
            'aliases' => ['Nama lain', 'list'],
            'min_stock' => ['Stok minimum', 'qty'],
            'show_in_store' => ['Tampil di toko', 'bool'],
            'available' => ['Tersedia', 'bool'],
            'recipe' => ['Resep', 'text'],
            'stock' => ['Stok', 'qty'],
        ],
        Contact::class => [
            'type' => ['Jenis', ContactType::class],
            'name' => ['Nama', 'text'],
            'phone' => ['Nomor WA', 'text'],
        ],
        Transaction::class => [
            'total' => ['Total', 'rupiah'],
            'description' => ['Keterangan', 'text'],
            'method' => ['Cara bayar', PaymentMethod::class],
            'contact' => ['Kontak', 'text'],
            'occurred_at' => ['Waktu', 'datetime'],
        ],
        User::class => [
            'name' => ['Nama', 'text'],
            'email' => ['Email', 'text'],
        ],
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $event = ActivityEvent::tryFrom((string) $this->event);

        return [
            'id' => $this->id,
            'at' => $this->created_at->toIso8601String(),
            'category' => $this->log_name,
            'event' => $this->event,
            'actor' => $this->causer?->getAttribute('name')
                ?? (in_array($event, [ActivityEvent::LoginFailed, ActivityEvent::Lockout], true) ? 'Tidak dikenal' : 'Sistem'),
            'source' => $this->getProperty('source'),
            'description' => $this->description,
            'detail' => $this->detail($event),
            'href' => match (true) {
                $this->subject instanceof Transaction => route('transactions.show', $this->subject),
                $this->subject instanceof Item && ! $this->subject->trashed() => route('items.edit', $this->subject),
                default => null,
            },
            'changes' => $this->changes(),
        ];
    }

    /**
     * Old → new per field. Only entries that compare two states have them (not "Menambah").
     *
     * @return list<array{label: string, old: string, new: string}>
     */
    private function changes(): array
    {
        $old = $this->attribute_changes?->get('old');
        $new = $this->attribute_changes?->get('attributes') ?? [];

        if (! is_array($old)) {
            return [];
        }

        $fields = self::FIELDS[Relation::getMorphedModel((string) $this->subject_type) ?? $this->subject_type] ?? [];
        $changes = [];

        foreach ($fields as $field => [$label, $format]) {
            if (! array_key_exists($field, $old) && ! array_key_exists($field, $new)) {
                continue;
            }

            [$before, $after] = [$this->format($format, $old[$field] ?? null), $this->format($format, $new[$field] ?? null)];

            // null → [] and the like read the same, so they tell the owner nothing.
            if ($before !== $after) {
                $changes[] = ['label' => $label, 'old' => $before, 'new' => $after];
            }
        }

        return $changes;
    }

    private function format(string $format, mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return 'kosong';
        }

        return match ($format) {
            'text' => (string) $value,
            'rupiah' => Format::rupiah((int) $value),
            'qty' => trim(Format::qty($value).' '.($this->subject?->getAttribute('unit') ?? '')),
            'bool' => $value ? 'Ya' : 'Tidak',
            'list' => implode(', ', (array) $value),
            'datetime' => self::dateTime($value),
            // An enum class: its label.
            default => $format::tryFrom((string) $value)?->label() ?? (string) $value,
        };
    }

    /**
     * "17 Okt 2026, 20.26" in WIB.
     */
    private static function dateTime(mixed $value): string
    {
        $date = Carbon::parse($value)->setTimezone(config('app.timezone'));
        $date->locale('id');

        return $date->translatedFormat('j M Y, H.i');
    }

    private function detail(?ActivityEvent $event): ?string
    {
        $reason = $this->getProperty('reason');
        $value = $this->getProperty('value');
        $total = $this->getProperty('total');
        $email = $this->getProperty('email');
        $ip = $this->getProperty('ip');

        $parts = array_filter([
            $event === ActivityEvent::Cancelled && $reason ? "Alasan: {$reason}" : null,
            $event === ActivityEvent::StockAdjusted && $value ? 'Nilai selisih '.Format::rupiah(abs((int) $value)) : null,
            $event === ActivityEvent::Created && $total !== null && $this->log_name === ActivityCategory::Transaksi->value ? 'Total '.Format::rupiah((int) $total) : null,
            $email ? "Email yang dicoba: {$email}" : null,
            $ip && $this->log_name === ActivityCategory::Akun->value ? "IP {$ip}" : null,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
