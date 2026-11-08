<?php

namespace Tests\Feature;

use App\Enums\ContactType;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Item;
use App\Services\Ai\AssistantUnavailable;
use App\Services\Ai\FormAssistant;
use App\Services\Ai\ItemDraft;
use App\Services\Ai\TransactionDraft;
use App\Services\BusinessFacts;
use App\Services\Catalog;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * PRD §3.2 "Pembacaan pesan": owner sentences through the real model
 * Excluded from the normal run (phpunit.xml), which also pins the gateway
 * driver. After changing the prompt or the model, run it with
 * `LLM_DRIVER=claude LOG_CHANNEL=single php artisan test --group=llm`; this
 * spends credit, and the tokens of each call land in laravel.log.
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
        ['buatkan saya kode python untuk menghitung beras yang saya beli 5 kg jadi berapa gram', null, ['form' => null, 'reply_starts' => 'Maaf, saya hanya bisa membantu']],
        // "Dicatat di mana?" questions get a guide topic, not a draft or a refusal.
        ['saya punya 200.000 uang di kas pribadi saya, saya perlu catat kemana ya? dan data apa yang saya perlu', null, ['form' => null, 'topic' => 'uang_pribadi_masuk']],
        ['kalau saya bayar belanja warung pakai uang sendiri, dicatat gimana?', null, ['form' => null, 'topic' => 'bayar_pakai_uang_pribadi']],
        ['beli kompor itu masuk biaya atau apa?', null, ['form' => null, 'topic' => 'beli_peralatan']],
        ['telur ada yang pecah 5 butir, gimana nyatatnya?', null, ['form' => null, 'topic' => 'stok_tidak_sesuai']],
        ['pinjam uang ke bank 5 juta dicatat di mana?', null, ['form' => null, 'topic' => 'belum_didukung']],
        // Where a feature lives, what a term means, and whether a product is worth keeping.
        ['di mana nyatet COA ya?', null, ['form' => null, 'topic' => 'daftar_akun']],
        ['saya punya utang ke supplier, dicatat di mana?', null, ['form' => null, 'topic' => 'bayar_utang_supplier']],
        ['salah catat penjualan tadi, gimana benerinnya?', null, ['form' => null, 'topic' => 'salah_catat']],
        ['cara buka toko online gimana?', null, ['form' => null, 'topic' => 'pesanan_toko']],
        ['apa itu HPP?', null, ['form' => null, 'reply_starts' => 'HPP: modal bahan']],
        ['prive itu maksudnya apa?', null, ['form' => null, 'reply_starts' => 'Prive: ']],
        ['hpp bulan ini berapa', null, ['form' => null, 'reply_starts' => 'HPP bulan ini']],
        ['es teh worth it nggak sih?', null, ['form' => null, 'reply_starts' => 'Es Teh belum laku']],
        // A printed receipt; real photos from owners go next to it (PRD §3.2: 15 photos, field ≥ 80%).
        ['', null, ['form' => 'transaksi', 'type' => 'pembelian', 'kind' => 'barang', 'method' => 'tunai', 'lines' => ['Beras' => 5, 'Telur' => 30], 'prices' => ['Beras' => 14000, 'Telur' => 2000]], 'nota-belanja.png'],
        ['untung bulan ini berapa?', null, ['form' => null, 'reply_starts' => 'Laba bersih bulan ini']],
        ['omzet minggu ini berapa ya', null, ['form' => null, 'reply_starts' => 'Penjualan minggu ini']],
        ['uang saya ada berapa sekarang', null, ['form' => null, 'reply_starts' => 'Uang usaha sekarang']],
        ['siapa aja yang belum bayar?', null, ['form' => null, 'reply_starts' => 'Tidak ada piutang']],
        ['hpp nasi goreng berapa', null, ['form' => null, 'reply_starts' => 'HPP Nasi Goreng']],
        ['kalau mau untung 40% nasgor dijual berapa?', null, ['form' => null, 'reply_starts' => 'HPP Nasi Goreng']],
    ];

    public function test_the_assistant_reads_owner_messages_well_enough(): void
    {
        // The eval measures reading, not 9Router's slow moments; each case's time is in the report.
        config(['services.llm.timeout' => 60, 'services.llm.vision_timeout' => 90]);
        $business = self::business();
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
            $wrong = array_keys(array_filter($expected, fn (mixed $want, string $key) => $key === 'reply_starts' ? ! str_starts_with((string) $observed[$key], (string) $want) : ($observed[$key] ?? null) != $want, ARRAY_FILTER_USE_BOTH));

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
            'model' => config('services.llm.driver') === 'claude' ? config('services.llm.claude.model') : config('services.llm.model'),
            'intent' => round($intent, 3),
            'fields' => round($fields, 3),
            'average_seconds' => round(array_sum(array_column($rows, 'seconds')) / count($rows), 1),
            'cases' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->assertGreaterThanOrEqual(0.95, $intent, "Intent below 95%, see {$report}");
        $this->assertGreaterThanOrEqual(0.90, $fields, "Fields below 90%, see {$report}");
    }

    /**
     * Follow-ups: what the owner means depends on the draft on screen and on
     * the turns before it. The first case is the owner's own conversation of
     * 7 Nov, where an older message in the history pulled the model away.
     */
    public function test_the_assistant_follows_up_on_the_draft_on_screen(): void
    {
        config(['services.llm.timeout' => 60]);
        $business = self::business();
        $items = $business->items()->get();
        $contacts = $business->contacts()->get();
        $moneyAccounts = $business->moneyAccounts()->get();
        $assistant = app(FormAssistant::class);
        $record = TransactionDraft::fill(['jenis' => 'pembelian', 'jenis_biaya' => 'barang', 'metode_bayar' => 'tunai', 'baris' => [
            ['item' => 'Air Mineral', 'qty' => 1, 'total' => 4000],
            ['item' => 'Cimory hazelnut', 'qty' => 1, 'total' => 8000],
            ['item' => 'Frestea madu', 'qty' => 1, 'total' => 6000],
            ['item' => 'Ice cream aice', 'qty' => 1, 'total' => 5000],
            ['item' => 'Kanzler', 'qty' => 1, 'total' => 10000],
        ]], [], $items, $contacts, $moneyAccounts)['values'];
        $older = [
            ['role' => 'user', 'text' => 'tambah menu mie goreng 14rb, pakai telur 1 dan beras 1 ons'],
            ['role' => 'assistant', 'text' => 'Masih ada yang kurang. Balas di sini atau lengkapi di form.'],
            ['role' => 'user', 'text' => 'Coba catat ini dong'],
            ['role' => 'assistant', 'text' => 'Masih ada yang kurang. Balas di sini atau lengkapi di form.'],
        ];
        $onRecord = ['form' => 'transaksi', 'values' => $record];
        $ask = fn (string $message, array $history, ?array $draft) => $assistant->reply($business->user, $message, null, $history, [], $draft);
        $goods = fn (array $reply) => array_column($reply['values']['items'] ?? [], 'name');
        $failures = [];
        $check = function (string $case, bool $passed, array $reply) use (&$failures) {
            if (! $passed) {
                $failures[] = "{$case}: form={$reply['form']} reply={$reply['reply']} values=".json_encode($reply['values'], JSON_UNESCAPED_UNICODE);
            }
        };

        $all = $ask('Bisa ngga kamu buatkan yang kurang ke daftar produk dulu?', $older, $onRecord);
        $check('yang kurang', $all['form'] === 'barang' && $goods($all) === ['Cimory hazelnut', 'Frestea madu', 'Ice cream aice', 'Kanzler'], $all);

        $named = $ask('Yang kurang kan kayak cimory hazelnut frestea dll', $older, $onRecord);
        $check('menyebut nama', $named['form'] === 'barang' && in_array('Frestea madu', $goods($named), true), $named);

        $sold = $ask('Semua dijual lagi', $older, ['form' => 'barang', 'values' => $all['values'] ?? []]);
        $check('semua dijual', array_unique(array_column($sold['values']['items'] ?? [], 'kind')) === ['produk'], $sold);

        $priced = $ask('cimory 12rb, frestea 8rb, aice 7rb, kanzler 15rb', $older, ['form' => 'barang', 'values' => $sold['values'] ?? []]);
        $check('harga jual', array_column($priced['values']['items'] ?? [], 'price') === [12000, 8000, 7000, 15000] && $priced['confirm'] === true, $priced);

        $method = $ask('bayarnya transfer', $older, $onRecord);
        $check('koreksi draf', $method['form'] === 'transaksi' && ($method['values']['method'] ?? '') === 'non_tunai' && count($method['values']['unknown'] ?? []) === 4, $method);

        $fixed = $ask('air mineralnya jadi 2 botol', $older, $onRecord);
        $check('koreksi baris', $fixed['form'] === 'transaksi' && count($fixed['values']['unknown'] ?? []) === 4, $fixed);

        $draft = ['form' => 'produk', 'values' => ItemDraft::fill(['jenis' => 'produk', 'nama' => 'Es Jeruk', 'harga' => 14000], [], $items)['values']];
        $other = $ask('Yogurt Hazelnut sebagai produk baru', [], $draft);
        $check('produk lain', ($other['values']['name'] ?? '') === 'Yogurt Hazelnut' && ($other['values']['price'] ?? null) === null, $other);

        $period = $ask('kalau bulan lalu?', [['role' => 'user', 'text' => 'untung bulan ini berapa?'], ['role' => 'assistant', 'text' => 'Laba bersih bulan ini Rp0.']], null);
        $check('periode lanjutan', str_starts_with($period['reply'], 'Laba bersih bulan lalu'), $period);

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    /**
     * Questions no fixed metric covers, read from the fact sheet. An answer
     * must quote what the sheet says, and one it refuses counts as a miss.
     */
    public function test_the_assistant_answers_from_the_fact_sheet(): void
    {
        config(['services.llm.timeout' => 60]);
        $business = self::business();
        $service = app(TransactionService::class);
        $teh = $business->items()->where('items.name', 'Es Teh')->sole();
        $air = $business->items()->where('items.name', 'Air Mineral')->sole();
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDays(9)->format('Y-m-d 09:00');
        $service->record($business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 3000000, 'occurred_at' => $lastMonth]);
        $service->record($business, ['type' => 'penjualan', 'method' => 'tunai', 'occurred_at' => $lastMonth, 'lines' => [['item_id' => $teh->id, 'qty' => 30, 'price' => 5000], ['item_id' => $air->id, 'qty' => 10, 'price' => 4000]]]);
        $service->record($business, ['type' => 'biaya', 'kind' => 'sewa', 'method' => 'tunai', 'amount' => 50000, 'occurred_at' => $lastMonth]);
        $service->record($business, ['type' => 'biaya', 'kind' => 'pemasaran', 'method' => 'tunai', 'amount' => 10000, 'occurred_at' => $lastMonth]);
        $sheet = app(BusinessFacts::class)->sheet($business);
        $on = function (string $pattern) use ($sheet) {
            preg_match($pattern, $sheet, $match);

            return $match[1] ?? '(tidak ada di lembar)';
        };
        $cases = [
            'berapa BEP usaha saya?' => $on('/BEP penjualan (Rp[\d.]+)/'),
            'bep es teh berapa gelas sebulan?' => $on('/Es Teh: ([\d.,]+ gelas) sebulan/'),
            'modal saya berapa sekarang?' => $on('/Modal pemilik \(aset dikurangi kewajiban\) (Rp[\d.]+)/'),
            'aset saya berapa?' => $on('/Total aset (Rp[\d.]+)/'),
            'stok beras tinggal berapa?' => '10 kg',
            'produk paling laris apa?' => 'Es Teh',
            'kenapa untung bulan ini turun?' => '',
            'biaya apa yang bisa dihemat?' => '',
            'hari apa paling ramai?' => '',
        ];
        $failures = [];

        foreach ($cases as $question => $expected) {
            $reply = app(FormAssistant::class)->reply($business->user, $question, null, [], [])['reply'];

            if (str_starts_with($reply, 'Angkanya belum') || str_starts_with($reply, 'Datanya belum') || ! str_contains($reply, $expected)) {
                $failures[] = "{$question} => mau \"{$expected}\", dapat: {$reply}";
            }
        }

        $unknown = app(FormAssistant::class)->reply($business->user, 'harga beras di pasar hari ini berapa?', null, [], [])['reply'];

        // Declining in any words is right; a price from nowhere is not.
        if (preg_match('/\d/', str_replace('Rp100', '', $unknown)) === 1 && ! str_contains($unknown, 'jual 3 nasi goreng')) {
            $failures[] = "harga pasar => dapat: {$unknown}";
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    private static function business(): Business
    {
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

        return $business;
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
            'reply_starts' => $reply['reply'],
            'topic' => $reply['guide']['topic'] ?? null,
            'contact' => $contacts->firstWhere('id', (int) ($values['contact'] ?? 0))->name ?? ($values['contact'] ?? null),
            'lines' => $rows('lines')->mapWithKeys(fn (array $line) => [$name($line['item_id']) => $number($line['qty'])])->all(),
            'prices' => $rows('lines')->mapWithKeys(fn (array $line) => [$name($line['item_id']) => $line['price']])->all(),
            'recipe' => $rows('recipe')->mapWithKeys(fn (array $line) => [$name($line['material_id']) => $number($line['qty'])])->all(),
            'opening_stock' => isset($values['opening_stock']) ? $number($values['opening_stock']) : null,
        ];
    }
}
