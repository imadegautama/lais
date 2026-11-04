<?php

namespace Tests\Feature;

use App\Enums\ContactType;
use App\Enums\GuideTopic;
use App\Enums\TransactionType;
use App\Models\Business;
use App\Models\Item;
use App\Models\Transaction;
use App\Services\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['services.llm.url' => 'http://9router.test/v1', 'services.llm.key' => 'test-key', 'services.llm.model' => 'test-model', 'services.llm.vision_model' => 'test-vision']);

        $this->business = Business::factory()->create();
        app(Catalog::class)->createMany($this->business, [
            ['name' => 'Beras', 'kind' => 'bahan', 'unit' => 'kg', 'opening_stock' => 10, 'opening_cost' => 14000],
            ['name' => 'Telur', 'kind' => 'bahan', 'unit' => 'butir', 'opening_stock' => 30, 'opening_cost' => 2000],
            ['name' => 'Air Mineral', 'kind' => 'produk', 'unit' => 'botol', 'price' => 4000, 'aliases' => ['aqua'], 'opening_stock' => 24, 'opening_cost' => 2500],
            ['name' => 'Nasi Goreng', 'kind' => 'komposisi', 'unit' => 'porsi', 'price' => 15000, 'aliases' => ['nasgor'], 'recipe' => [['name' => 'Beras', 'qty' => 0.15], ['name' => 'Telur', 'qty' => 1]]],
        ]);
        $this->business->contacts()->create(['type' => ContactType::Pelanggan, 'name' => 'Bu Sari']);
    }

    public function test_a_sale_in_words_becomes_a_card_from_the_business_catalog_and_saves_nothing(): void
    {
        $this->model(['isi_transaksi', ['jenis' => 'penjualan', 'metode_bayar' => 'non_tunai', 'baris' => [['item' => 'nasgor', 'qty' => 3], ['item' => 'Aqua', 'qty' => 1]]]]);
        // Another business's item never reaches the prompt.
        Item::factory()->for(Business::factory())->create(['name' => 'Kopi Susu Tetangga']);

        $response = $this->ask('jual 3 nasgor 1 aqua qris', 'transaksi')->assertOk();

        $this->assertSame([
            ['item_id' => (string) $this->item('Nasi Goreng')->id, 'qty' => '3', 'price' => 15000],
            ['item_id' => (string) $this->item('Air Mineral')->id, 'qty' => '1', 'price' => 4000],
        ], $response->json('values.lines'));
        $this->assertSame('Penjualan', $response->json('card.title'));
        $this->assertSame([
            ['name' => 'Nasi Goreng', 'qty' => 3, 'unit' => 'porsi', 'price' => 15000, 'amount' => 45000],
            ['name' => 'Air Mineral', 'qty' => 1, 'unit' => 'botol', 'price' => 4000, 'amount' => 4000],
        ], $response->json('card.lines'));
        $this->assertSame(['label' => 'Total', 'amount' => 49000], $response->json('card.total'));
        $this->assertSame('Non-tunai ke Rekening', $response->json('card.notes.0'));
        $this->assertSame(['Simpan catatan ini?', true, [], '/transaksi/baru?jenis=penjualan', null], [
            $response->json('reply'), $response->json('confirm'), $response->json('missing'), $response->json('target'), $response->json('saved'),
        ]);
        $this->assertSame(0, Transaction::query()->count());

        Http::assertSent(function (Request $request) {
            $system = $request['messages'][0]['content'];

            return $request->url() === 'http://9router.test/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['stream'] === false && $request['tool_choice'] === 'required'
                && str_contains($system, 'Nasi Goreng (alias: nasgor)') && ! str_contains($system, 'Kopi Susu Tetangga')
                && $request['messages'][1] === ['role' => 'user', 'content' => 'jual 3 nasgor 1 aqua qris'];
        });
    }

    public function test_confirming_the_draft_records_it_without_asking_the_model(): void
    {
        $this->model(['isi_transaksi', ['jenis' => 'pembelian', 'metode_bayar' => 'tunai', 'baris' => [['item' => 'beras', 'qty' => 5, 'total' => 70000]]]]);
        $draft = $this->ask('beli beras 5 kg 70rb tunai', null)
            ->assertJsonPath('values.lines.0.price', 14000)
            ->assertJsonPath('card.total.amount', 70000)
            ->json('values');

        $response = $this->ask('iya', null, draft: ['form' => 'transaksi', 'values' => $draft])->assertOk();

        $transaction = Transaction::query()->sole();
        $this->assertSame([TransactionType::Pembelian, 70000], [$transaction->type, $transaction->total]);
        $response->assertJsonPath('reply', 'Belanja tersimpan.')
            ->assertJsonPath('saved', ['label' => 'Lihat transaksi', 'href' => "/transaksi/{$transaction->id}"])
            ->assertJsonPath('card', null);
        Http::assertSentCount(1);
    }

    public function test_a_recipe_product_is_saved_with_its_ingredients_once_confirmed(): void
    {
        $this->model(['isi_produk', ['jenis' => 'komposisi', 'nama' => 'mie goreng', 'harga' => 14000, 'resep' => [['bahan' => 'telur', 'qty' => 1], ['bahan' => 'Beras', 'qty' => 0.1]]]]);
        $response = $this->ask('tambah mie goreng 14rb pakai telur 1, beras 1 ons', 'produk')->assertOk()
            ->assertJsonPath('card.title', 'Mie Goreng');

        $this->assertSame([
            ['name' => 'Telur', 'qty' => 1, 'unit' => 'butir', 'price' => null, 'amount' => null],
            ['name' => 'Beras', 'qty' => 0.1, 'unit' => 'kg', 'price' => null, 'amount' => null],
        ], $response->json('card.lines'));
        $response->assertJsonPath('card.total', ['label' => 'Harga jual per porsi', 'amount' => 14000])
            ->assertJsonPath('confirm', true);

        $saved = $this->ask('oke gas', 'produk', draft: ['form' => 'produk', 'values' => $response->json('values')])->assertOk();

        $item = $this->item('Mie Goreng');
        $this->assertSame([14000, 2], [$item->price, $item->recipeLines()->count()]);
        $saved->assertJsonPath('saved', ['label' => 'Lihat produk', 'href' => "/produk/{$item->id}/ubah"]);
    }

    public function test_a_missing_ingredient_blocks_saving_and_is_named(): void
    {
        $this->model(['isi_produk', ['jenis' => 'komposisi', 'nama' => 'Mie Goreng', 'harga' => 14000, 'resep' => [['bahan' => 'Telur', 'qty' => 1], ['bahan' => 'Kecap', 'qty' => 1]]]]);

        $this->ask('tambah mie goreng 14rb pakai telur 1 dan kecap', 'produk')->assertOk()
            ->assertJsonPath('missing', ['Bahan "Kecap" belum ada. Tambahkan dulu sebagai bahan baku.'])
            ->assertJsonPath('confirm', false)
            ->assertJsonPath('reply', 'Masih ada yang kurang. Balas di sini atau lengkapi di form.');
    }

    public function test_what_simpan_would_refuse_is_missing_and_a_follow_up_completes_the_draft(): void
    {
        $this->model(
            ['isi_transaksi', ['jenis' => 'penjualan', 'metode_bayar' => 'kredit', 'baris' => [['item' => 'Nasi Goreng', 'qty' => 2]]]],
            ['isi_transaksi', ['jenis' => 'penjualan', 'kontak' => 'bu sari']],
        );

        $draft = $this->ask('2 nasgor belum bayar', 'transaksi')->assertOk()
            ->assertJsonPath('missing', ['Pilih atau tambahkan nama pelanggan yang belum bayar.'])
            ->assertJsonPath('confirm', false)
            ->json('values');

        // Confirming an incomplete draft saves nothing.
        $this->ask('iya', 'transaksi', draft: ['form' => 'transaksi', 'values' => $draft])->assertOk()
            ->assertJsonPath('reply', 'Belum bisa disimpan. Lengkapi dulu yang kurang.')
            ->assertJsonPath('missing', ['Pilih atau tambahkan nama pelanggan yang belum bayar.']);
        $this->assertSame(0, Transaction::query()->count());

        $this->ask('itu bu sari', 'transaksi', draft: ['form' => 'transaksi', 'values' => $draft])->assertOk()
            ->assertJsonPath('values.contact', (string) $this->business->contacts()->sole()->id)
            ->assertJsonPath('values.lines.0.qty', '2')
            ->assertJsonPath('confirm', true);
    }

    public function test_the_receipt_a_draft_was_read_from_is_kept_as_its_proof(): void
    {
        Storage::fake('local');
        $this->model(['isi_transaksi', ['jenis' => 'penjualan', 'metode_bayar' => 'tunai', 'baris' => [['item' => 'Nasi Goreng', 'qty' => 1]]]]);
        $draft = $this->ask('jual 1 nasgor tunai', 'transaksi')->json('values');

        $this->ask('iya', 'transaksi', draft: ['form' => 'transaksi', 'values' => $draft, 'photo' => 'data:image/png;base64,bm90IGFuIGltYWdl'])
            ->assertUnprocessable();
        $this->ask('iya', 'transaksi', draft: ['form' => 'transaksi', 'values' => $draft, 'photo' => $this->photo()])
            ->assertOk()
            ->assertJsonPath('reply', 'Penjualan tersimpan.');

        $proof = Transaction::query()->sole()->proof_path;
        $this->assertStringStartsWith("bukti/{$this->business->id}/", $proof);
        Storage::disk('local')->assertExists($proof);
    }

    public function test_a_draft_from_the_browser_is_judged_again_before_saving(): void
    {
        $this->model(['isi_transaksi', ['jenis' => 'penjualan', 'metode_bayar' => 'tunai', 'baris' => [['item' => 'Nasi Goreng', 'qty' => 1]]]]);
        $draft = $this->ask('jual 1 nasgor tunai', 'transaksi')->json('values');
        $draft['lines'][0]['item_id'] = (string) Item::factory()->for(Business::factory())->create()->id;

        $this->ask('iya', 'transaksi', draft: ['form' => 'transaksi', 'values' => $draft])->assertOk()
            ->assertJsonPath('saved', null)
            ->assertJsonPath('missing.0', 'Barang ini tidak dijual atau sudah diarsipkan.');
        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_only_a_plain_yes_confirms(): void
    {
        $this->model(
            ['isi_transaksi', ['jenis' => 'penjualan', 'metode_bayar' => 'tunai', 'baris' => [['item' => 'Nasi Goreng', 'qty' => 1]]]],
            ['isi_transaksi', ['jenis' => 'penjualan', 'baris' => [['item' => 'Nasi Goreng', 'qty' => 4]]]],
            ['jawab_singkat', ['jenis' => 'terima_kasih']],
        );
        $draft = $this->ask('jual 1 nasgor tunai', 'transaksi')->json('values');

        $this->ask('iya tapi 4 porsi', 'transaksi', draft: ['form' => 'transaksi', 'values' => $draft])->assertOk()
            ->assertJsonPath('values.lines.0.qty', '4');
        // Without a draft shown, "iya" is just a message.
        $this->ask('iya', 'transaksi')->assertOk()->assertJsonPath('saved', null);

        $this->assertSame(0, Transaction::query()->count());
        Http::assertSentCount(3);
    }

    public function test_requests_outside_recording_get_a_fixed_reply(): void
    {
        $this->model(['jawab_singkat', ['jenis' => 'di_luar_tugas', 'teks' => '```python print(5 * 1000) ```']]);

        $this->ask('buatkan saya kode python untuk menghitung beras', null)->assertOk()
            ->assertJsonPath('reply', 'Maaf, saya hanya bisa membantu mencatat transaksi dan produk baru. Ceritakan saja, misalnya "jual 3 nasi goreng tunai".')
            ->assertJsonPath('values', null);
    }

    public function test_a_question_back_carries_its_choices(): void
    {
        $this->model(['tanya_balik', ['pertanyaan' => 'Beras dicatat dalam kg. 4 kodi itu berapa kg?', 'pilihan' => ['80 kg', '4 kg']]]);

        $this->ask('beli beras 4 kodi', 'transaksi')->assertOk()
            ->assertJsonPath('reply', 'Beras dicatat dalam kg. 4 kodi itu berapa kg?')
            ->assertJsonPath('options', ['80 kg', '4 kg'])
            ->assertJsonPath('values', null);
    }

    public function test_a_where_do_i_record_this_question_gets_the_written_guide_and_a_draft_to_open(): void
    {
        $this->model(['jelaskan', ['topik' => 'uang_pribadi_masuk', 'nominal' => 200000]]);

        $response = $this->ask('saya punya 200.000 di kas pribadi, catat ke mana ya?', null)->assertOk()
            ->assertJsonPath('reply', GuideTopic::UangPribadiMasuk->explanation())
            ->assertJsonPath('guide.topic', 'uang_pribadi_masuk')
            ->assertJsonPath('guide.needs', GuideTopic::UangPribadiMasuk->needs())
            ->assertJsonPath('guide.offer.label', 'Catat setor modal Rp200.000')
            ->assertJsonPath('guide.offer.values.type', 'modal')
            ->assertJsonPath('guide.offer.values.kind', 'setor')
            ->assertJsonPath('guide.offer.card.total.amount', 200000)
            ->assertJsonPath('guide.offer.confirm', true);

        // The draft only becomes the one to save once the owner opens it.
        $this->assertSame([null, null, false], [$response->json('values'), $response->json('card'), $response->json('confirm')]);
        $this->assertSame(0, Transaction::query()->count());
        Http::assertSent(fn (Request $request) => str_contains($request['messages'][0]['content'], '- stok_tidak_sesuai: '));
    }

    public function test_guides_without_a_draft_point_to_the_right_page(): void
    {
        $this->model(
            ['jelaskan', ['topik' => 'stok_tidak_sesuai']],
            ['jelaskan', ['topik' => 'baru_mulai']],
            ['jelaskan', ['topik' => 'baru_mulai']],
            ['jelaskan', ['topik' => 'pinjaman_bank']],
        );

        $this->ask('telur pecah 5 butir gimana nyatatnya?', null)
            ->assertJsonPath('link', ['label' => 'Buka Produk & stok', 'href' => '/produk'])
            ->assertJsonPath('guide.offer', null);
        $this->ask('saya baru mulai, uang yang sudah ada dicatat di mana?', null)
            ->assertJsonPath('link.href', '/onboarding');

        $this->business->update(['onboarded_at' => now()]);
        $this->ask('saya baru mulai, uang yang sudah ada dicatat di mana?', null)
            ->assertJsonPath('link.href', '/transaksi/baru?jenis=saldo_awal');

        $this->ask('pinjam uang ke bank dicatat di mana?', null)
            ->assertJsonPath('reply', GuideTopic::BelumDidukung->explanation())
            ->assertJsonPath('link', null);
    }

    public function test_report_questions_wait_for_the_next_sprint_and_point_to_the_dashboard(): void
    {
        $this->model(['jawab_singkat', ['jenis' => 'pertanyaan_laporan']]);

        $this->ask('untung bulan ini berapa?', null)->assertOk()
            ->assertJsonPath('reply', 'Pertanyaan soal laporan, misalnya untung atau produk terlaris, belum bisa saya jawab. Untuk sekarang, angkanya ada di Dashboard.')
            ->assertJsonPath('link', ['label' => 'Buka Dashboard', 'href' => '/dashboard']);
    }

    public function test_a_receipt_photo_is_copied_by_the_vision_model_then_recorded_like_a_message(): void
    {
        $this->model(
            ['tulis_nota', ['bukan_nota' => false, 'toko' => 'Toko Makmur Jaya', 'tanggal' => '2026-10-27 09:14', 'cara_bayar' => 'tunai', 'baris' => [
                ['nama' => 'Beras 5 kg', 'jumlah' => 1, 'total' => 70000],
                ['nama' => 'Telur', 'jumlah' => 30, 'total' => 60000],
            ]]],
            ['isi_transaksi', ['jenis' => 'pembelian', 'jenis_biaya' => 'barang', 'metode_bayar' => 'tunai', 'kontak' => 'Toko Makmur Jaya', 'tanggal' => '2026-10-27 09:14', 'baris' => [
                ['item' => 'Beras', 'qty' => 5, 'total' => 70000],
                ['item' => 'Telur', 'qty' => 30, 'total' => 60000],
            ]]],
        );

        $response = $this->ask('', null, photo: $this->photo())->assertOk();

        $this->assertSame([
            ['name' => 'Beras', 'qty' => 5, 'unit' => 'kg', 'price' => 14000, 'amount' => 70000],
            ['name' => 'Telur', 'qty' => 30, 'unit' => 'butir', 'price' => 2000, 'amount' => 60000],
        ], $response->json('card.lines'));
        $response->assertJsonPath('card.total.amount', 130000)
            ->assertJsonPath('values.contact', 'Toko Makmur Jaya')
            ->assertJsonPath('confirm', true);
        $this->assertSame(0, Transaction::query()->count());

        // The image only goes to the short copying step; the catalog step gets the copy as text.
        $recorded = Http::recorded();
        $this->assertSame('test-vision', $recorded[0][0]['model']);
        $this->assertSame(['type' => 'image_url', 'image_url' => ['url' => $this->photo()]], $recorded[0][0]['messages'][1]['content'][1]);
        $this->assertStringNotContainsString('Nasi Goreng', $recorded[0][0]['messages'][0]['content']);
        $this->assertSame('test-model', $recorded[1][0]['model']);
        $this->assertSame(
            'Dari foto nota Toko Makmur Jaya, tanggal 2026-10-27 09:14, dibayar tunai: Beras 5 kg jumlah 1 total 70000; Telur jumlah 30 total 60000.',
            $recorded[1][0]['messages'][1]['content'],
        );
    }

    public function test_a_photo_is_always_read_and_one_that_is_not_a_receipt_says_so(): void
    {
        $this->model(
            ['isi_transaksi', ['jenis' => 'penjualan', 'metode_bayar' => 'tunai', 'baris' => [['item' => 'Nasi Goreng', 'qty' => 1]]]],
            ['tulis_nota', ['bukan_nota' => true]],
        );
        $draft = $this->ask('jual 1 nasgor tunai', 'transaksi')->json('values');

        // "iya" with a new photo is something new to read, not a confirmation of the old draft.
        $this->ask('iya', 'transaksi', draft: ['form' => 'transaksi', 'values' => $draft], photo: $this->photo())->assertOk()
            ->assertJsonPath('reply', 'Foto ini tidak terbaca sebagai nota belanja. Coba foto notanya lagi dengan lebih terang, atau ketik saja yang mau dicatat.')
            ->assertJsonPath('values', null);

        $this->assertSame(0, Transaction::query()->count());
        Http::assertSentCount(2);
    }

    public function test_only_real_images_up_to_five_megabytes_are_read(): void
    {
        Http::fake();

        $this->ask('', null, photo: 'data:text/plain;base64,'.base64_encode('bukan gambar'))->assertUnprocessable()
            ->assertJsonValidationErrors(['photo' => 'Foto harus berupa gambar JPG, PNG, atau WebP sampai 5 MB.']);
        $this->ask('', null, photo: 'data:image/png;base64,'.base64_encode('bukan gambar'))->assertJsonValidationErrors('photo');
        $this->ask('', null, photo: 'data:image/png;base64,'.base64_encode(str_repeat('a', 5 * 1024 * 1024 + 1)))->assertJsonValidationErrors('photo');
        $this->ask('', null)->assertJsonValidationErrors(['message' => 'Tulis dulu apa yang mau dicatat, atau kirim foto notanya.']);

        Http::assertNothingSent();
    }

    public function test_values_the_form_cannot_take_are_dropped(): void
    {
        $this->model(['isi_transaksi', ['jenis' => 'saldo_awal', 'metode_bayar' => 'bitcoin', 'baris' => [['item' => 'Kopi Susu Tetangga', 'qty' => 1]]]]);
        Item::factory()->for(Business::factory())->create(['name' => 'Kopi Susu Tetangga']);

        $this->ask('jual kopi susu tetangga pakai bitcoin', 'transaksi')->assertOk()
            ->assertJsonPath('values.type', 'penjualan')
            ->assertJsonPath('values.method', 'tunai')
            ->assertJsonPath('values.lines.0.item_id', '')
            ->assertJsonPath('missing.0', '"Kopi Susu Tetangga" tidak ada di daftar produk.');
    }

    public function test_when_the_model_fails_the_owner_is_told_to_fill_the_form_by_hand(): void
    {
        Http::fakeSequence()->push(['error' => 'overloaded'], 500)->pushFailedConnection('timed out');

        $this->ask('jual 1 nasgor', 'transaksi')->assertStatus(503)
            ->assertJsonPath('message', 'AI sedang tidak bisa dipakai. Isi form secara manual dulu, atau coba lagi sebentar lagi.');
        $this->ask('jual 1 nasgor', 'transaksi')->assertStatus(503);
    }

    public function test_guests_cannot_ask(): void
    {
        $this->postJson(route('assistant.store'), ['message' => 'jual 1 nasgor'])->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array{form: string, values: mixed}|null  $draft
     * @return TestResponse<JsonResponse>
     */
    private function ask(string $message, ?string $form, array $current = [], ?array $draft = null, ?string $photo = null): TestResponse
    {
        return $this->actingAs($this->business->user)->postJson(route('assistant.store'), [
            'message' => $message,
            'form' => $form,
            'current' => $current,
            'draft' => $draft,
            'photo' => $photo,
        ]);
    }

    /** The eval's printed receipt, as the browser sends it. */
    private function photo(): string
    {
        return 'data:image/png;base64,'.base64_encode((string) file_get_contents(base_path('tests/Fixtures/nota-belanja.png')));
    }

    /**
     * 9Router's answers, one forced tool call per turn.
     *
     * @param  array{0: string, 1: array<string, mixed>}  ...$turns  [tool, arguments]
     */
    private function model(array ...$turns): void
    {
        $sequence = Http::fakeSequence();

        foreach ($turns as [$name, $args]) {
            $sequence->push(['choices' => [['message' => [
                'role' => 'assistant',
                'tool_calls' => [['id' => 'call_1', 'type' => 'function', 'function' => ['name' => $name, 'arguments' => json_encode($args)]]],
            ], 'finish_reason' => 'tool_calls']]]);
        }
    }

    private function item(string $name): Item
    {
        return Item::query()->where('business_id', $this->business->id)->where('name', $name)->sole();
    }
}
