<?php

namespace Tests\Feature;

use App\Enums\ContactType;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Item;
use App\Services\Ai\AssistantUnavailable;
use App\Services\Ai\FormAssistant;
use App\Services\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * PRD §3.2 "Pembacaan pesan": owner sentences through the real model
 * (9Router, .env LLM_*). Excluded from the normal run (phpunit.xml); run it
 * after changing the prompt or the model with `php artisan test --group=llm`.
 * Each run writes its report to storage/eval/.
 */
#[Group('llm')]
class AssistantEvalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * [message, open form, what the filled form must hold, receipt photo in
     * tests/Fixtures]. Lines and recipes are compared by name and quantity,
     * contacts by name.
     *
     * @var list<array{0: string, 1: string|null, 2: array<string, mixed>, 3?: string}>
     */
    private const CASES = [
        ['jual 3 nasgor 2 es teh qris', 'transaksi', ['form' => 'transaksi', 'type' => 'penjualan', 'method' => 'non_tunai', 'lines' => ['Nasi Goreng' => 3, 'Es Teh' => 2]]],
        ['nasgor 2 bungkus, aqua 1, tunai', 'transaksi', ['form' => 'transaksi', 'type' => 'penjualan', 'method' => 'tunai', 'lines' => ['Nasi Goreng' => 2, 'Air Mineral' => 1]]],
        ['bu sari ngutang 2 nasgor', 'transaksi', ['form' => 'transaksi', 'type' => 'penjualan', 'method' => 'kredit', 'contact' => 'Bu Sari', 'lines' => ['Nasi Goreng' => 2]]],
        ['jual 1 nasgor harga 18rb tunai', 'transaksi', ['form' => 'transaksi', 'method' => 'tunai', 'prices' => ['Nasi Goreng' => 18000]]],
        ['beli beras 5 kg 70rb di toko makmur', null, ['form' => 'transaksi', 'type' => 'pembelian', 'kind' => 'barang', 'contact' => 'Toko Makmur', 'lines' => ['Beras' => 5], 'prices' => ['Beras' => 14000]]],
        ['beli telur 30 butir 60rb tunai', 'transaksi', ['form' => 'transaksi', 'type' => 'pembelian', 'method' => 'tunai', 'lines' => ['Telur' => 30], 'prices' => ['Telur' => 2000]]],
        ['bayar listrik 350rb', 'transaksi', ['form' => 'transaksi', 'type' => 'biaya', 'kind' => 'listrik_air', 'amount' => 350000]],
        ['gaji karyawan 1,5jt transfer', 'transaksi', ['form' => 'transaksi', 'type' => 'biaya', 'kind' => 'upah', 'method' => 'non_tunai', 'amount' => 1500000]],
        ['setor modal 2jt tunai', 'transaksi', ['form' => 'transaksi', 'type' => 'modal', 'kind' => 'setor', 'amount' => 2000000]],
        ['ambil uang usaha 200rb buat belanja rumah', 'transaksi', ['form' => 'transaksi', 'type' => 'modal', 'kind' => 'prive', 'amount' => 200000]],
        ['bu sari bayar utangnya 30rb', 'transaksi', ['form' => 'transaksi', 'type' => 'utang_piutang', 'kind' => 'terima_piutang', 'contact' => 'Bu Sari', 'amount' => 30000]],
        ['tambah menu mie goreng 14rb pakai telur 1 dan beras 1 ons', 'produk', ['form' => 'produk', 'kind' => 'komposisi', 'name' => 'Mie Goreng', 'price' => 14000, 'recipe' => ['Telur' => 1, 'Beras' => 0.1]]],
        ['tambah produk kerupuk 2rb, stok 50 bungkus, modal 1000 per bungkus', 'produk', ['form' => 'produk', 'kind' => 'produk', 'price' => 2000, 'opening_stock' => 50, 'opening_cost' => 1000]],
        ['halo, selamat pagi', null, ['form' => null]],
        // A unit that does not fit the item's own unit is asked back, not converted.
        ['beli beras 4 kodi', 'transaksi', ['form' => null]],
        ['buatkan saya kode python untuk menghitung beras yang saya beli 5 kg jadi berapa gram', null, ['form' => null, 'reply' => 'Maaf, saya hanya bisa membantu mencatat transaksi dan produk baru. Ceritakan saja, misalnya "jual 3 nasi goreng tunai".']],
        // "Dicatat di mana?" questions get a guide topic, not a draft or a refusal.
        ['saya punya 200.000 uang di kas pribadi saya, saya perlu catat kemana ya? dan data apa yang saya perlu', null, ['form' => null, 'topic' => 'uang_pribadi_masuk']],
        ['kalau saya bayar belanja warung pakai uang sendiri, dicatat gimana?', null, ['form' => null, 'topic' => 'bayar_pakai_uang_pribadi']],
        ['beli kompor itu masuk biaya atau apa?', null, ['form' => null, 'topic' => 'beli_peralatan']],
        ['telur ada yang pecah 5 butir, gimana nyatatnya?', null, ['form' => null, 'topic' => 'stok_tidak_sesuai']],
        ['pinjam uang ke bank 5 juta dicatat di mana?', null, ['form' => null, 'topic' => 'belum_didukung']],
        // A printed receipt; real photos from owners go next to it (PRD §3.2: 15 photos, field ≥ 80%).
        ['', null, ['form' => 'transaksi', 'type' => 'pembelian', 'kind' => 'barang', 'method' => 'tunai', 'lines' => ['Beras' => 5, 'Telur' => 30], 'prices' => ['Beras' => 14000, 'Telur' => 2000]], 'nota-belanja.png'],
        ['untung bulan ini berapa?', null, ['form' => null, 'reply' => 'Pertanyaan soal laporan, misalnya untung atau produk terlaris, belum bisa saya jawab. Untuk sekarang, angkanya ada di Dashboard.']],
    ];

    public function test_the_assistant_reads_owner_messages_well_enough(): void
    {
        // The eval measures reading, not 9Router's slow moments; each case's time is in the report.
        config(['services.llm.timeout' => 60, 'services.llm.vision_timeout' => 90]);
        $business = Business::factory()->create();
        app(Catalog::class)->createMany($business, [
            ['name' => 'Beras', 'kind' => 'bahan', 'unit' => 'kg', 'opening_stock' => 10, 'opening_cost' => 14000],
            ['name' => 'Telur', 'kind' => 'bahan', 'unit' => 'butir', 'opening_stock' => 30, 'opening_cost' => 2000],
            ['name' => 'Air Mineral', 'kind' => 'produk', 'unit' => 'botol', 'price' => 4000, 'aliases' => ['aqua'], 'opening_stock' => 24, 'opening_cost' => 2500],
            ['name' => 'Es Teh', 'kind' => 'produk', 'unit' => 'gelas', 'price' => 5000, 'opening_stock' => 40, 'opening_cost' => 1500],
            ['name' => 'Nasi Goreng', 'kind' => 'komposisi', 'unit' => 'porsi', 'price' => 15000, 'aliases' => ['nasgor'], 'recipe' => [['name' => 'Beras', 'qty' => 0.15], ['name' => 'Telur', 'qty' => 1]]],
        ]);
        $business->contacts()->create(['type' => ContactType::Pelanggan, 'name' => 'Bu Sari']);
        $business->contacts()->create(['type' => ContactType::Supplier, 'name' => 'Toko Makmur']);
        $items = $business->items()->get();
        $contacts = $business->contacts()->get();

        $rows = [];

        foreach (self::CASES as $case) {
            [$message, $form, $expected] = $case;
            $file = $case[3] ?? null;
            $photo = $file === null ? null : 'data:image/png;base64,'.base64_encode((string) file_get_contents(base_path("tests/Fixtures/{$file}")));
            $start = microtime(true);

            try {
                $reply = app(FormAssistant::class)->reply($business->user, $message, $form, [], [], photo: $photo);
            } catch (AssistantUnavailable $exception) {
                // A slow or failed answer is a miss on every field, not the end of the run.
                $reply = ['reply' => $exception->getMessage(), 'form' => 'gagal', 'values' => null, 'guide' => null];
            }

            $observed = self::observed($reply, $items, $contacts);
            $wrong = array_keys(array_filter($expected, fn (mixed $want, string $key) => ($observed[$key] ?? null) != $want, ARRAY_FILTER_USE_BOTH));

            $rows[] = [
                'message' => $file === null ? $message : "(foto {$file}) {$message}",
                'seconds' => round(microtime(true) - $start, 1),
                'intent' => ! in_array('form', $wrong, true),
                'fields' => count($expected),
                'wrong' => $wrong,
                'reply' => $reply['reply'],
                'observed' => array_intersect_key($observed, $expected),
            ];
        }

        $intent = count(array_filter(array_column($rows, 'intent'))) / count($rows);
        $fields = 1 - array_sum(array_map(fn (array $row) => count($row['wrong']), $rows)) / array_sum(array_column($rows, 'fields'));
        $report = storage_path('eval/asisten-'.now()->format('Ymd-His').'.json');
        File::put($report, (string) json_encode([
            'model' => config('services.llm.model'),
            'intent' => round($intent, 3),
            'fields' => round($fields, 3),
            'average_seconds' => round(array_sum(array_column($rows, 'seconds')) / count($rows), 1),
            'cases' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->assertGreaterThanOrEqual(0.95, $intent, "Intent below 95%, see {$report}");
        $this->assertGreaterThanOrEqual(0.90, $fields, "Fields below 90%, see {$report}");
    }

    /**
     * The reply as the eval compares it: names instead of ids, quantities as numbers.
     *
     * @param  array{reply: string, form: string|null, values: array<string, mixed>|null, guide: array{topic: string}|null}  $reply
     * @param  Collection<int, Item>  $items
     * @param  Collection<int, Contact>  $contacts
     * @return array<string, mixed>
     */
    private static function observed(array $reply, Collection $items, Collection $contacts): array
    {
        $values = $reply['values'] ?? [];
        $name = fn (mixed $id) => $items->firstWhere('id', (int) $id)->name ?? '?';
        $number = fn (mixed $text) => (float) str_replace(',', '.', (string) $text);
        $rows = fn (string $key) => collect(is_array($values[$key] ?? null) ? $values[$key] : []);

        return [
            ...$values,
            'form' => $reply['form'],
            'reply' => $reply['reply'],
            'topic' => $reply['guide']['topic'] ?? null,
            'contact' => $contacts->firstWhere('id', (int) ($values['contact'] ?? 0))->name ?? ($values['contact'] ?? null),
            'lines' => $rows('lines')->mapWithKeys(fn (array $line) => [$name($line['item_id']) => $number($line['qty'])])->all(),
            'prices' => $rows('lines')->mapWithKeys(fn (array $line) => [$name($line['item_id']) => $line['price']])->all(),
            'recipe' => $rows('recipe')->mapWithKeys(fn (array $line) => [$name($line['material_id']) => $number($line['qty'])])->all(),
            'opening_stock' => isset($values['opening_stock']) ? $number($values['opening_stock']) : null,
        ];
    }
}
