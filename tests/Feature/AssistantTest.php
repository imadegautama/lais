<?php

namespace Tests\Feature;

use App\Enums\ContactType;
use App\Enums\GuideTopic;
use App\Enums\MoneyAccountKind;
use App\Enums\TransactionType;
use App\Models\Business;
use App\Models\Item;
use App\Models\Transaction;
use App\Services\Ai\FormAssistant;
use App\Services\Ai\TransactionDraft;
use App\Services\Catalog;
use App\Services\ReportAnswers;
use App\Services\TransactionService;
use App\Support\Format;
use App\Support\ReportRange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
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

    public function test_a_saved_sale_says_which_stock_now_needs_buying(): void
    {
        $this->item('Telur')->update(['min_stock' => 35]);
        $this->model(['isi_transaksi', ['jenis' => 'penjualan', 'metode_bayar' => 'tunai', 'baris' => [['item' => 'nasgor', 'qty' => 2]]]]);
        $draft = $this->ask('jual 2 nasgor tunai', null)->json('values');

        // Two portions use 2 telur (30 → 28, under its minimum of 35); beras has no minimum and too little history to guess.
        $this->ask('iya', null, draft: ['form' => 'transaksi', 'values' => $draft])
            ->assertJsonPath('reply', "Penjualan tersimpan.\nStok Telur tinggal 28 butir (batas minimum 35).");
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

    public function test_lines_written_as_nama_and_jumlah_are_read_like_the_schema_and_a_recipe_means_komposisi(): void
    {
        $this->model(
            ['isi_transaksi', ['jenis' => 'penjualan', 'metode_bayar' => 'tunai', 'baris' => [['nama' => 'Air Mineral', 'jumlah' => 2, 'satuan' => 'botol']]]],
            ['isi_produk', ['jenis' => 'produk', 'nama' => 'Mie Goreng', 'harga' => 14000, 'resep' => [['nama' => 'Telur', 'jumlah' => 2, 'satuan' => 'butir']]]],
        );

        $this->ask('jual 2 air mineral tunai', 'transaksi')->assertOk()
            ->assertJsonPath('values.lines', [['item_id' => (string) $this->item('Air Mineral')->id, 'qty' => '2', 'price' => 4000]]);

        $this->ask('tambah mie goreng 14rb pakai telur 2', 'produk')->assertOk()
            ->assertJsonPath('values.kind', 'komposisi')
            ->assertJsonPath('values.recipe', [['material_id' => (string) $this->item('Telur')->id, 'qty' => '2']]);
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
            ->assertJsonPath('reply', 'Maaf, saya hanya bisa membantu mencatat transaksi dan produk baru, menjawab angka usaha, serta menjelaskan istilah dan cara memakai Lais. Ceritakan saja, misalnya "jual 3 nasi goreng tunai", "untung bulan ini berapa?", atau "apa itu HPP?".')
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

    public function test_why_profit_and_cash_differ_skips_the_model(): void
    {
        // The opening stock was bought with the owner's money: profit 0, cash the same, nothing to explain.
        $this->ask('kenapa laba naik tapi kas turun?', null)->assertOk()
            ->assertJsonPath('reply', app(ReportAnswers::class)->profitVsCash($this->business))
            ->assertJsonPath('link', ['label' => 'Buka Perubahan modal', 'href' => '/laporan/perubahan-modal']);
    }

    public function test_numbers_are_read_from_the_books_and_the_model_only_picks_which(): void
    {
        $water = $this->item('Air Mineral');
        $transactions = app(TransactionService::class);
        $transactions->record($this->business, ['type' => 'penjualan', 'method' => 'tunai', 'lines' => [['item_id' => $water->id, 'qty' => 2, 'price' => 4000]]]);
        $transactions->record($this->business, ['type' => 'penjualan', 'method' => 'kredit', 'contact_id' => $this->business->contacts()->sole()->id, 'lines' => [['item_id' => $water->id, 'qty' => 1, 'price' => 4000]]]);
        $transactions->record($this->business, ['type' => 'biaya', 'kind' => 'listrik_air', 'method' => 'tunai', 'amount' => 1000]);
        $this->model(
            ['tanya_angka', ['metrik' => 'penjualan', 'periode' => 'hari_ini']],
            ['tanya_angka', ['metrik' => 'laba']],
            ['tanya_angka', ['metrik' => 'hpp', 'periode' => 'tahun_kabisat']],
            ['tanya_angka', ['metrik' => 'biaya', 'periode' => 'bulan_ini']],
            ['tanya_angka', ['metrik' => 'kas']],
            ['tanya_angka', ['metrik' => 'piutang']],
            ['tanya_angka', ['metrik' => 'utang']],
            ['tanya_angka', ['metrik' => 'cuaca']],
            ['jawab_singkat', ['jenis' => 'pertanyaan_laporan']],
        );

        $this->ask('penjualan hari ini berapa', null)->assertOk()
            ->assertJsonPath('reply', 'Penjualan hari ini Rp12.000 dari 2 transaksi.')
            ->assertJsonPath('link', ['label' => 'Buka Laba rugi', 'href' => '/laporan/laba-rugi?periode=hari_ini'])
            ->assertJsonPath('values', null);
        // 3 bottles at Rp2.500: 12.000 − 7.500 − 1.000.
        $this->ask('untung berapa', null)->assertJsonPath('reply', "Laba bersih bulan ini Rp3.500.\nPenjualan Rp12.000, modal barang (HPP) Rp7.500, biaya Rp1.000.");
        // A period the system does not know falls back to this month.
        $this->ask('hpp berapa', null)->assertJsonPath('reply', 'HPP bulan ini Rp7.500 dari penjualan Rp12.000, jadi laba kotornya Rp4.500.');
        $this->ask('biaya bulan ini', null)->assertJsonPath('reply', "Biaya bulan ini Rp1.000:\n• Listrik & air: Rp1.000");
        $this->ask('kas ada berapa', null)->assertJsonPath('reply', "Uang usaha sekarang Rp7.000:\n• Kas tunai: Rp7.000\n• Rekening: Rp0")
            ->assertJsonPath('link.href', '/rekening');
        $this->ask('siapa yang belum bayar', null)->assertJsonPath('reply', "Piutang sekarang Rp4.000:\n• Bu Sari: Rp4.000");
        $this->ask('utang saya berapa', null)->assertJsonPath('reply', 'Tidak ada utang yang belum dibayar.')
            ->assertJsonPath('link.href', '/utang-piutang?jenis=utang');
        $this->ask('berapa itu', null)->assertJsonPath('reply', 'Mau tahu angka apa? Misalnya penjualan, untung, biaya, kas, piutang, atau utang.');
        $this->ask('produk terlaris apa', null)->assertJsonPath('link.href', '/laporan');
    }

    public function test_hpp_and_a_suggested_price_are_worked_out_from_the_catalog(): void
    {
        $this->model(
            ['tanya_harga', ['produk' => 'nasgor', 'target_margin' => 40]],
            ['tanya_harga', ['produk' => 'Nasi Goreng', 'target_margin' => 140]],
            ['tanya_harga', ['produk' => 'Beras']],
            ['tanya_harga', ['produk' => 'Martabak']],
        );
        $cost = $this->item('Nasi Goreng')->load('recipeLines.material')->unitCost();

        $this->ask('harga jual nasgor kalau margin 40%', null)->assertOk()
            ->assertJsonPath('reply', 'HPP Nasi Goreng Rp'.number_format($cost, 0, ',', '.')." per porsi. Ini dari resep, belum termasuk biaya seperti gas dan upah.\n"
                .'Harga jual sekarang Rp15.000, margin Rp'.number_format(15000 - $cost, 0, ',', '.').' ('.Format::decimalInput(round((15000 - $cost) / 15000 * 100, 1)).'%).'."\n"
                .'Supaya margin 40%, harga jual minimal '.Format::rupiah(Item::priceForMargin($cost, 40)).'.')
            ->assertJsonPath('link.label', 'Buka produk');
        // A margin of 100% or more cannot be priced, so the usual choices are offered.
        $this->ask('harga nasgor margin 140%', null)->assertSee('Margin 30%: ', false)->assertSee('Margin 50%: ', false);
        // Bahan are not sold: their cost, with no price to suggest.
        $this->ask('modal beras berapa', null)->assertJsonPath('reply', 'Biaya rata-rata Beras Rp14.000 per kg.');
        $this->ask('hpp martabak', null)->assertJsonPath('reply', '"Martabak" tidak ada di daftar produk.');
    }

    public function test_a_rekening_left_to_pick_is_offered_and_taken_without_the_model(): void
    {
        $gopay = $this->business->moneyAccounts()->create(['kind' => MoneyAccountKind::NonTunai, 'name' => 'GoPay']);
        $this->business->update(['qris_money_account_id' => $gopay->id]);
        $this->model(['isi_transaksi', ['jenis' => 'penjualan', 'metode_bayar' => 'non_tunai', 'baris' => [['item' => 'aqua', 'qty' => 1]]]]);

        $draft = $this->ask('jual 1 aqua transfer', null)->assertOk()
            ->assertJsonPath('missing', [TransactionDraft::PICK_ACCOUNT])
            ->assertJsonPath('options', ['Rekening', 'GoPay']);
        // The model is told where QRIS lands, so "qris" alone needs no rekening named.
        Http::assertSent(fn (Request $request) => str_contains(json_encode($request['messages']), 'GoPay (non_tunai, QRIS masuk ke sini)'));

        $this->ask('gopay', null, [], ['form' => 'transaksi', 'values' => $draft->json('values')])
            ->assertJsonPath('values.money_account_id', (string) $gopay->id)
            ->assertJsonPath('confirm', true)
            ->assertJsonPath('options', []);
        $this->assertCount(1, Http::recorded(fn (Request $request) => str_starts_with($request->url(), 'http://9router.test')));
    }

    public function test_a_question_on_a_report_page_is_answered_from_that_reports_period(): void
    {
        $from = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
        app(TransactionService::class)->record($this->business, ['type' => 'penjualan', 'method' => 'tunai', 'occurred_at' => $from->addDays(2)->format('Y-m-d 10:00'), 'lines' => [['item_id' => $this->item('Air Mineral')->id, 'qty' => 3, 'price' => 4000]]]);
        $this->model(['tanya_data', ['hal' => 'laporan ini']], ['jawab', ['jawaban' => 'Penjualan di periode ini Rp12.000.']]);
        $range = ReportRange::between($from, $from->addDays(14));

        $this->actingAs($this->business->user)->postJson(route('assistant.store'), [
            'message' => 'jelaskan laporan ini',
            'report' => ['page' => 'reports/profit-and-loss', 'dari' => $from->toDateString(), 'sampai' => $from->addDays(14)->toDateString()],
        ])->assertOk()->assertJsonPath('reply', 'Penjualan di periode ini Rp12.000.');

        [$picking, $answering] = Http::recorded(fn (Request $request) => str_starts_with($request->url(), 'http://9router.test'))->map(fn (array $pair) => json_encode($pair[0]->data(), JSON_UNESCAPED_UNICODE))->values()->all();
        $this->assertStringContainsString("Halaman yang dibuka: laporan Laba rugi, {$range->label}", $picking);
        $this->assertStringContainsString("LAPORAN YANG DIBUKA: Laba rugi, {$range->label}", $answering);
        $this->assertStringContainsString('Penjualan Rp12.000 dari 1 transaksi', $answering);

        // Only the report pages the panel knows.
        $this->postJson(route('assistant.store'), ['message' => 'halo', 'report' => ['page' => 'settings/security']])->assertUnprocessable();
    }

    public function test_a_conversation_remembers_ten_turns_and_a_draft_only_its_last_two(): void
    {
        $history = array_map(fn (int $n) => ['role' => $n % 2 ? 'user' : 'assistant', 'text' => "giliran {$n}"], range(1, 14));
        $this->model(['jawab_singkat', ['jenis' => 'sapaan']], ['jawab_singkat', ['jenis' => 'sapaan']]);
        $sent = fn () => json_encode(Http::recorded(fn (Request $request) => str_starts_with($request->url(), 'http://9router.test'))->last()[0]->data());

        $this->ask('lalu?', null, history: $history)->assertOk();
        $this->assertStringContainsString('giliran 5', $sent());
        $this->assertStringNotContainsString('giliran 4"', $sent());

        $this->ask('lalu?', null, history: $history, draft: ['form' => 'transaksi', 'values' => ['type' => 'penjualan']])->assertOk();
        $this->assertStringContainsString('giliran 13', $sent());
        $this->assertStringNotContainsString('giliran 12', $sent());
    }

    public function test_where_a_feature_lives_is_answered_with_its_page(): void
    {
        $this->model(
            ['jelaskan', ['topik' => 'daftar_akun']],
            ['jelaskan', ['topik' => 'bayar_utang_supplier']],
        );

        $this->ask('di mana nyatet COA?', null)->assertOk()
            ->assertJsonPath('reply', GuideTopic::DaftarAkun->explanation())
            ->assertJsonPath('link', ['label' => 'Buka Daftar akun', 'href' => '/laporan/coa'])
            ->assertJsonPath('guide.offer', null);
        // A topic with a draft to open also says where the list lives.
        $this->ask('saya punya utang ke supplier, dicatat di mana?', null)
            ->assertJsonPath('guide.offer.label', 'Catat bayar utang')
            ->assertJsonPath('link', ['label' => 'Lihat utang', 'href' => '/utang-piutang?jenis=utang']);
    }

    public function test_a_term_is_explained_in_the_same_words_as_on_screen(): void
    {
        $this->model(
            ['artikan', ['istilah' => 'cogs']],
            ['artikan', ['istilah' => 'drawings']],
            ['artikan', ['istilah' => 'ebitda']],
        );

        $this->ask('apa itu HPP?', null)->assertOk()
            ->assertJsonPath('reply', 'HPP: modal bahan dan barang dari produk yang terjual.')
            ->assertJsonPath('link', ['label' => 'Buka Laba rugi', 'href' => '/laporan/laba-rugi']);
        // A definition that does not start with its own name gets it in front.
        $this->ask('prive itu apa', null)
            ->assertJsonPath('reply', 'Prive: uang usaha yang diambil untuk keperluan pribadi (prive). Tidak mengurangi laba, tapi mengurangi modal.');
        $this->ask('apa itu ebitda', null)
            ->assertJsonPath('reply', 'Istilah itu belum ada di daftar Lais. Coba tanya dengan nama lain, misalnya "apa itu HPP?" atau "apa itu piutang?".')
            ->assertJsonPath('link', null);
    }

    public function test_whether_a_product_is_worth_it_comes_from_its_margin_and_sales(): void
    {
        $this->model(
            ['nilai_produk', ['produk' => 'aqua']],
            ['nilai_produk', ['produk' => 'aqua']],
            ['nilai_produk', ['produk' => 'nasgor']],
        );
        $closing = 'Penilaian ini belum menghitung biaya tetap seperti sewa, gas, dan upah.';

        $this->ask('aqua worth it ga?', null)->assertOk()
            ->assertJsonPath('reply', "Air Mineral belum laku: tidak terjual dalam 30 hari terakhir. Stoknya 24 botol senilai Rp60.000 masih tertahan.\n"
                ."Harga jual Rp4.000, modal Rp2.500 per botol: untung Rp1.500 (margin 37,5%, sehat).\n{$closing}")
            ->assertJsonPath('link', ['label' => 'Buka laporan Produk', 'href' => '/laporan/produk']);

        app(TransactionService::class)->record($this->business, ['type' => 'penjualan', 'method' => 'tunai', 'lines' => [['item_id' => $this->item('Air Mineral')->id, 'qty' => 2, 'price' => 4000]]]);

        $this->ask('aqua worth it ga?', null)
            ->assertJsonPath('reply', "Air Mineral layak dipertahankan.\n"
                ."Harga jual Rp4.000, modal Rp2.500 per botol: untung Rp1.500 (margin 37,5%, sehat).\n"
                ."30 hari terakhir terjual 2 botol, laba kotor Rp3.000, 100% dari semua laba kotor (peringkat 1 dari 1 produk).\n{$closing}");
        // Made to order from a recipe: no stock to hold up, cost from the recipe (0,15 kg beras + 1 telur = Rp4.100).
        $this->ask('nasgor masih layak dijual?', null)
            ->assertJsonPath('reply', "Nasi Goreng belum laku: tidak terjual dalam 30 hari terakhir.\n"
                ."Harga jual Rp15.000, modal Rp4.100 per porsi: untung Rp10.900 (margin 72,7%, sehat).\n{$closing}");
    }

    public function test_a_losing_or_thin_product_gets_the_price_that_fixes_it(): void
    {
        $water = $this->item('Air Mineral');
        app(TransactionService::class)->record($this->business, ['type' => 'penjualan', 'method' => 'tunai', 'lines' => [['item_id' => $water->id, 'qty' => 2, 'price' => 4000]]]);
        $this->model(
            ...array_fill(0, 3, ['nilai_produk', ['produk' => 'Air Mineral']]),
            ...[['nilai_produk', ['produk' => 'Beras']], ['nilai_produk', ['produk' => 'Martabak']]],
        );
        $sold = '30 hari terakhir terjual 2 botol, laba kotor Rp3.000, 100% dari semua laba kotor (peringkat 1 dari 1 produk).';
        $fix = 'Supaya margin 30%, harga jual minimal Rp4.000.';
        $closing = 'Penilaian ini belum menghitung biaya tetap seperti sewa, gas, dan upah.';

        $water->update(['price' => 2000]);
        $this->ask('air mineral worth it?', null)->assertOk()
            ->assertJsonPath('reply', "Air Mineral belum layak: setiap botol terjual malah rugi.\n"
                ."Harga jual Rp2.000, modal Rp2.500 per botol: rugi Rp500 (margin -25%, rugi).\n{$sold}\n{$fix}\n{$closing}");

        $water->update(['price' => 2900]);
        $this->ask('air mineral worth it?', null)
            ->assertJsonPath('reply', "Air Mineral masih untung, tapi tipis.\n"
                ."Harga jual Rp2.900, modal Rp2.500 per botol: untung Rp400 (margin 13,8%, tipis).\n{$sold}\n{$fix}\n{$closing}");

        $water->update(['price' => null]);
        $this->ask('air mineral worth it?', null)
            ->assertJsonPath('reply', 'Air Mineral belum bisa dinilai karena harga jualnya belum ada. Lengkapi dulu, lalu tanya lagi.')
            ->assertJsonPath('link.label', 'Buka produk');
        $this->ask('beras worth it?', null)
            ->assertJsonPath('reply', 'Beras adalah bahan yang tidak dijual, jadi yang dinilai adalah produk yang memakainya.');
        $this->ask('martabak worth it?', null)->assertJsonPath('reply', '"Martabak" tidak ada di daftar produk.');
    }

    public function test_a_price_for_a_margin_is_rounded_up_to_rp500(): void
    {
        // Same numbers as cost.test.ts.
        $this->assertSame(12000, Item::priceForMargin(7200, 40));
        $this->assertSame(10500, Item::priceForMargin(7200, 30));
        $this->assertSame(14500, Item::priceForMargin(7200, 50));
        $this->assertSame(1000, Item::priceForMargin(699, 30));
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

    public function test_goods_that_are_not_listed_stop_the_draft_from_being_saved_by_saying_iya(): void
    {
        $this->model(['isi_transaksi', ['jenis' => 'pembelian', 'jenis_biaya' => 'barang', 'metode_bayar' => 'tunai', 'baris' => [
            ['item' => 'Beras', 'qty' => 5, 'total' => 70000],
            ['item' => 'Frestea Madu', 'qty' => 2, 'total' => 12000],
        ]]]);
        $draft = $this->ask('Dari foto nota: Beras dan Frestea', null)->assertOk()->assertJsonPath('confirm', false)->json('values');

        $this->ask('iya', null, draft: ['form' => 'transaksi', 'values' => $draft])->assertOk()
            ->assertJsonPath('reply', 'Belum bisa disimpan. Lengkapi dulu yang kurang.')
            ->assertJsonPath('missing.0', '"Frestea Madu" tidak ada di daftar produk.');

        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_goods_that_are_not_listed_stay_on_the_card_so_the_total_matches_the_receipt(): void
    {
        $this->model(['isi_transaksi', ['jenis' => 'pembelian', 'jenis_biaya' => 'barang', 'metode_bayar' => 'tunai', 'baris' => [
            ['item' => 'Beras', 'qty' => 5, 'total' => 70000],
            ['item' => 'Frestea Madu', 'qty' => 2, 'total' => 12000],
        ]]]);

        $response = $this->ask('Dari foto nota: Beras dan Frestea', null)->assertOk();

        $this->assertSame(['name' => 'Frestea Madu', 'qty' => 2, 'unit' => null, 'price' => 6000, 'amount' => 12000], $response->json('card.lines.1'));
        $response->assertJsonPath('card.total.amount', 82000)
            ->assertJsonPath('options', ['Daftarkan barang yang belum ada', 'Catat yang ada saja'])
            ->assertJsonPath('values.unknown.0.name', 'Frestea Madu');
    }

    public function test_the_next_message_is_told_what_the_draft_holds_and_what_is_not_listed(): void
    {
        $this->model(
            ['isi_transaksi', ['jenis' => 'pembelian', 'jenis_biaya' => 'barang', 'baris' => [
                ['item' => 'Beras', 'qty' => 5, 'total' => 70000],
                ['item' => 'Frestea Madu', 'qty' => 2, 'total' => 12000],
            ]]],
            ['tanya_balik', ['pertanyaan' => 'Yang mana?']],
        );
        $draft = $this->ask('Dari foto nota: Beras dan Frestea', null)->json('values');

        $this->ask('buatkan yang kurang', null, draft: ['form' => 'transaksi', 'values' => $draft])->assertOk();

        $system = Http::recorded()[1][0]['messages'][0]['content'];
        $this->assertStringContainsString('Barang di draf: Beras', $system);
        $this->assertStringContainsString('Belum ada di daftar: Frestea Madu', $system);
    }

    public function test_with_a_draft_open_older_requests_do_not_reach_the_model(): void
    {
        $this->model(['isi_transaksi', ['jenis' => 'penjualan', 'metode_bayar' => 'non_tunai']]);
        $draft = ['form' => 'transaksi', 'values' => ['type' => 'penjualan']];
        $history = [
            ['role' => 'user', 'text' => 'tambah menu mie goreng 14rb'],
            ['role' => 'assistant', 'text' => 'Masih ada yang kurang.'],
            ['role' => 'user', 'text' => 'jual 1 nasgor'],
            ['role' => 'assistant', 'text' => 'Simpan catatan ini?'],
        ];

        $this->actingAs($this->business->user)->postJson(route('assistant.store'), ['message' => 'bayarnya transfer', 'history' => $history, 'draft' => $draft])->assertOk();

        $sent = Http::recorded()[0][0]['messages'];
        $this->assertSame(['jual 1 nasgor', 'Simpan catatan ini?', 'bayarnya transfer'], array_column(array_slice($sent, 1), 'content'));
    }

    public function test_only_the_owner_can_drop_goods_that_are_not_listed(): void
    {
        $this->model(
            ['isi_transaksi', ['jenis' => 'pembelian', 'jenis_biaya' => 'barang', 'metode_bayar' => 'tunai', 'baris' => [
                ['item' => 'Beras', 'qty' => 5, 'total' => 70000],
                ['item' => 'Frestea Madu', 'qty' => 2, 'total' => 12000],
            ]]],
            // A correction that leaves it out must not lose it.
            ['isi_transaksi', ['jenis' => 'pembelian', 'baris' => [['item' => 'Beras', 'qty' => 6, 'total' => 84000]]]],
        );
        $values = $this->ask('Dari foto nota', null)->json('values');

        $values = $this->ask('berasnya 6 kg', null, draft: ['form' => 'transaksi', 'values' => $values])->assertOk()
            ->assertJsonPath('values.unknown.0.name', 'Frestea Madu')
            ->assertJsonPath('confirm', false)
            ->json('values');

        foreach (['Catat yang ada saja', 'yang belum ada tidak usah, catat yang ada aja', 'yg kurang gak usah'] as $words) {
            $this->assertTrue(FormAssistant::skipsMissing($words), $words);
        }
        foreach (['Yang kurang kan kayak cimory hazelnut frestea dll', 'daftarkan yang kurang', 'beras ada 5 kg'] as $words) {
            $this->assertFalse(FormAssistant::skipsMissing($words), $words);
        }

        Http::fake();
        $this->ask('Catat yang ada saja', null, draft: ['form' => 'transaksi', 'values' => $values])->assertOk()
            ->assertJsonPath('values.unknown', [])
            ->assertJsonPath('card.total.amount', 84000)
            ->assertJsonPath('confirm', true);
        Http::assertNothingSent();
    }

    public function test_a_draft_of_only_unlisted_goods_does_not_ask_to_pick_lines(): void
    {
        $this->model(['isi_transaksi', ['jenis' => 'pembelian', 'jenis_biaya' => 'barang', 'baris' => [['item' => 'Kopi Uji', 'qty' => 3, 'total' => 9000]]]]);

        $this->ask('beli kopi uji', null)->assertOk()
            ->assertJsonPath('missing', ['"Kopi Uji" tidak ada di daftar produk.'])
            ->assertJsonPath('card.total.amount', 9000);
    }

    public function test_goods_from_a_draft_are_listed_on_one_card_and_the_record_comes_back_whole(): void
    {
        $this->model(
            ['isi_transaksi', ['jenis' => 'pembelian', 'jenis_biaya' => 'barang', 'metode_bayar' => 'tunai', 'baris' => [
                ['item' => 'Beras', 'qty' => 5, 'total' => 70000],
                ['item' => 'Frestea Madu', 'qty' => 2, 'total' => 12000],
                ['item' => 'Gula Pasir', 'qty' => 1, 'total' => 15000],
            ]]],
            ['tambah_ke_daftar', ['barang' => [['nama' => 'Frestea Madu'], ['nama' => 'Gula Pasir']]]],
            ['tambah_ke_daftar', ['barang' => [['nama' => 'Frestea Madu', 'jenis' => 'produk', 'satuan' => 'botol'], ['nama' => 'Gula Pasir', 'jenis' => 'bahan', 'satuan' => 'kg']]]],
            ['tambah_ke_daftar', ['barang' => [['nama' => 'frestea', 'harga_jual' => 8000]]]],
        );
        $record = $this->ask('Dari foto nota', null)->json('values');

        $asked = $this->ask('Daftarkan barang yang belum ada', null, draft: ['form' => 'transaksi', 'values' => $record])->assertOk()
            ->assertJsonPath('form', 'barang')
            ->assertJsonPath('confirm', false)
            ->assertJsonPath('options', ['Semua dijual lagi', 'Semua bahan baku']);
        $sorted = $this->ask('frestea dijual, gula bahan', null, draft: ['form' => 'barang', 'values' => $asked->json('values')])->assertOk()
            ->assertJsonPath('reply', 'Berapa harga jualnya? Tulis saja, misalnya "frestea madu 8rb".')
            ->assertJsonPath('confirm', false);
        $priced = $this->ask('frestea 8rb', null, draft: ['form' => 'barang', 'values' => $sorted->json('values')])->assertOk()
            ->assertJsonPath('confirm', true)
            ->assertJsonPath('card.lines.0.amount', 8000);
        $this->assertSame(0, Item::query()->whereIn('name', ['Frestea Madu', 'Gula Pasir'])->count());

        $back = $this->ask('Simpan', null, draft: ['form' => 'barang', 'values' => $priced->json('values')])->assertOk()
            ->assertJsonPath('form', 'transaksi')
            ->assertJsonPath('reply', '2 barang masuk daftar produk. Simpan catatan ini?')
            ->assertJsonPath('card.total.amount', 97000)
            ->assertJsonPath('confirm', true)
            ->assertJsonPath('values.unknown', []);
        $this->assertSame(['bahan', 'botol', 8000], [$this->item('Gula Pasir')->kind->value, $this->item('Frestea Madu')->unit, $this->item('Frestea Madu')->price]);

        $this->ask('iya', null, draft: ['form' => 'transaksi', 'values' => $back->json('values')])->assertOk()
            ->assertJsonPath('reply', 'Belanja tersimpan.');

        $this->assertSame(97000, Transaction::query()->sole()->total);
        $this->assertEquals(2, $this->item('Frestea Madu')->fresh()->stock);
    }

    public function test_a_goods_draft_is_saved_whole_or_not_at_all(): void
    {
        $values = ['record' => ['type' => 'pembelian', 'kind' => 'barang'], 'items' => [
            ['name' => 'Frestea Madu', 'kind' => 'bahan', 'unit' => 'botol', 'price' => null],
            ['name' => 'Beras', 'kind' => 'bahan', 'unit' => 'kg', 'price' => null],
        ]];
        Http::fake();

        $this->ask('simpan', null, draft: ['form' => 'barang', 'values' => $values])->assertOk()
            ->assertJsonPath('reply', 'Belum bisa disimpan. Lengkapi dulu yang kurang.')
            ->assertJsonPath('missing.0', '"Beras": Nama ini sudah dipakai produk lain.');

        $this->assertSame(0, Item::query()->where('name', 'Frestea Madu')->count());
        Http::assertNothingSent();
    }

    public function test_goods_for_sale_need_a_price_and_only_goods_of_the_draft_can_be_named(): void
    {
        $values = ['record' => ['type' => 'pembelian', 'kind' => 'barang'], 'items' => [
            ['name' => 'Frestea Madu', 'kind' => 'produk', 'unit' => 'botol', 'price' => null],
        ]];
        $this->model(['tambah_ke_daftar', ['barang' => [['nama' => 'Mie Goreng', 'jenis' => 'produk', 'harga_jual' => 14000]]]]);

        $this->ask('tambah mie goreng', null, draft: ['form' => 'barang', 'values' => $values])->assertOk()
            ->assertJsonCount(1, 'values.items')
            ->assertJsonPath('values.items.0.price', null)
            ->assertJsonPath('confirm', false)
            ->assertJsonPath('missing.0', '"Frestea Madu": Harga jual wajib diisi.');
    }

    public function test_without_a_draft_there_are_no_goods_to_list(): void
    {
        $this->model(['tambah_ke_daftar', ['barang' => [['nama' => 'Mie Goreng']]]]);

        $this->ask('daftarkan yang kurang', null)->assertOk()
            ->assertJsonPath('form', null)
            ->assertJsonPath('reply', 'Tidak ada barang yang menunggu didaftarkan. Kirim dulu nota atau catatan belanjanya.');
    }

    public function test_another_product_does_not_inherit_the_price_of_the_last_draft(): void
    {
        $this->model(
            ['isi_produk', ['jenis' => 'produk', 'nama' => 'Es Jeruk', 'harga' => 14000]],
            ['isi_produk', ['jenis' => 'produk', 'nama' => 'Yogurt Hazelnut', 'produk_lain' => true]],
            ['isi_produk', ['nama' => 'Es Jeruk Peras']],
        );
        $first = $this->ask('tambah es jeruk 14rb', null)->json('values');

        $this->ask('yogurt hazelnut sebagai produk baru', null, draft: ['form' => 'produk', 'values' => $first])->assertOk()
            ->assertJsonPath('values.name', 'Yogurt Hazelnut')
            ->assertJsonPath('values.price', null);

        // Renaming the same product keeps what it had.
        $this->ask('ganti namanya es jeruk peras', null, draft: ['form' => 'produk', 'values' => $first])->assertOk()
            ->assertJsonPath('values.price', 14000);
    }

    public function test_a_question_about_the_business_is_answered_from_the_fact_sheet(): void
    {
        $this->model(['tanya_data', []], ['jawab', ['jawaban' => 'Stok beras 10 kg.']]);

        $this->ask('stok beras tinggal berapa?', null)->assertOk()
            ->assertJsonPath('reply', 'Stok beras 10 kg.')
            ->assertJsonPath('link.href', '/laporan');

        $recorded = Http::recorded();
        $this->assertStringNotContainsString('LEMBAR FAKTA', $recorded[0][0]['messages'][0]['content']);
        $this->assertStringContainsString('LEMBAR FAKTA', $recorded[1][0]['messages'][0]['content']);
        $this->assertStringContainsString('Beras: 10 kg', $recorded[1][0]['messages'][0]['content']);
        $this->assertSame('stok beras tinggal berapa?', $recorded[1][0]['messages'][1]['content']);
    }

    public function test_an_answer_with_a_figure_that_is_not_on_the_sheet_is_not_shown(): void
    {
        $this->model(['tanya_data', []], ['jawab', ['jawaban' => 'Untung Anda Rp9.876.543.']]);

        $response = $this->ask('untung kira-kira berapa?', null)->assertOk()
            ->assertJsonPath('reply', 'Angkanya belum bisa saya pastikan, jadi tidak saya sebutkan. Cek langsung di Laporan.');

        $this->assertStringNotContainsString('9.876.543', (string) $response->getContent());
    }

    public function test_a_question_the_sheet_cannot_answer_says_so(): void
    {
        $this->model(['tanya_data', []], ['jawab', ['jawaban' => '', 'tidak_ada' => true]]);

        $this->ask('harga beras di pasar berapa?', null)->assertOk()
            ->assertJsonPath('reply', 'Datanya belum ada di pembukuan, jadi belum bisa saya jawab. Coba lihat di Laporan.');
    }

    public function test_recording_a_sale_never_carries_the_fact_sheet(): void
    {
        $this->model(['isi_transaksi', ['jenis' => 'penjualan', 'metode_bayar' => 'tunai', 'baris' => [['item' => 'Nasi Goreng', 'qty' => 1]]]]);

        $this->ask('jual 1 nasgor tunai', 'transaksi')->assertOk();

        Http::assertSentCount(1);
        $this->assertStringNotContainsString('LEMBAR FAKTA', Http::recorded()[0][0]['messages'][0]['content']);
    }

    public function test_a_business_past_its_daily_limit_stops_reaching_the_model(): void
    {
        foreach (range(1, 200) as $call) {
            RateLimiter::hit("asisten-harian:{$this->business->id}", 86400);
        }
        Http::fake();

        $this->ask('jual 1 nasgor', 'transaksi')->assertServiceUnavailable();

        Http::assertNothingSent();
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
    private function ask(string $message, ?string $form, array $current = [], ?array $draft = null, ?string $photo = null, array $history = []): TestResponse
    {
        return $this->actingAs($this->business->user)->postJson(route('assistant.store'), [
            'history' => $history,
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
