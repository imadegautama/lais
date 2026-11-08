<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TransactionSource;
use App\Models\Business;
use App\Models\Item;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WaMessage;
use App\Services\Catalog;
use App\Services\Orders;
use App\Services\TransactionService;
use App\Services\WhatsApp\Linking;
use App\Support\OrderMessages;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class WhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'rahasia-webhook-uji-yang-panjang-sekali';

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.llm.url' => 'http://9router.test/v1',
            'services.llm.key' => 'test-key',
            'services.llm.model' => 'test-model',
            'services.bot.channel' => 'whatsapp',
            'services.gowa.url' => 'http://gowa.test',
            'services.gowa.basic_auth' => 'admin:sandi',
            'services.gowa.device_id' => 'lais',
            'services.gowa.webhook_secret' => self::SECRET,
            'services.gowa.bot_number' => '6281100000000',
        ]);
        // GOWA: sending, a media download, and the file it then serves. Other hosts fall through to later fakes.
        Http::fake(fn (Request $request) => match (true) {
            ! str_starts_with($request->url(), 'http://gowa.test') => null,
            str_contains($request->url(), '/download') => Http::response(['code' => 'SUCCESS', 'results' => ['file_path' => 'statics/media/nota.png']]),
            str_contains($request->url(), '/statics/') => Http::response((string) file_get_contents(base_path('tests/Fixtures/nota-belanja.png'))),
            default => Http::response(['code' => 'SUCCESS', 'results' => ['message_id' => 'OUT1']]),
        });
        $this->owner = Business::factory()->create(['name' => 'Warung Ani'])->user;
    }

    public function test_the_webhook_refuses_a_missing_or_wrong_signature(): void
    {
        $body = json_encode($this->event('jual 1 nasi goreng'));

        $this->call('POST', route('webhooks.gowa'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertUnauthorized();
        $this->call('POST', route('webhooks.gowa'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=salah'], $body)->assertUnauthorized();
        $this->assertSame(0, WaMessage::query()->count());
    }

    public function test_only_a_persons_own_messages_are_kept_and_each_only_once(): void
    {
        $this->deliver($this->event('halo', id: 'M1'))->assertNoContent();
        $this->deliver($this->event('halo', id: 'M1'))->assertNoContent();
        $this->deliver($this->event('dari bot', id: 'M2', fromMe: true));
        $this->deliver($this->event('di grup', id: 'M3', chat: '120363000@g.us'));
        $this->deliver(['event' => 'message.ack', 'payload' => ['id' => 'M4']]);

        $this->assertSame(['M1'], WaMessage::query()->where('direction', 'in')->pluck('message_id')->all());
    }

    public function test_an_owner_links_their_number_by_sending_the_code(): void
    {
        $this->actingAs($this->owner)->post(route('whatsapp.code'))->assertRedirect();
        $code = app(Linking::class)->pending($this->owner)['code'];

        $this->get(route('whatsapp.edit'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/whatsapp')
            ->where('pending.code', $code)
            ->where('linked', null)
            ->where('channel', 'WhatsApp')
            ->where('contact', '+6281100000000'));

        $this->deliver($this->event("lais {$code}", from: '6281234567890@s.whatsapp.net', lid: '99887766@lid'));

        $this->owner->refresh();
        $this->assertSame(['6281234567890', '99887766@lid'], [$this->owner->whatsapp, $this->owner->whatsapp_lid]);
        $this->assertNotNull($this->owner->whatsapp_linked_at);
        $this->assertSent('6281234567890@s.whatsapp.net', 'Bot sudah terhubung.');
        $this->get(route('whatsapp.edit'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('linked.phone', '6281234567890')
            ->where('pending', null));

        // A sender known only by its linked id is still the owner.
        $this->deliver($this->event('halo', id: 'M9', from: '99887766@lid'));
        $this->assertSame($this->owner->id, WaMessage::query()->where('message_id', 'M9')->value('user_id'));
    }

    public function test_a_wrong_expired_or_taken_code_links_nothing(): void
    {
        $this->deliver($this->event('LAIS 123456'));
        $this->assertSent('6281234567890@s.whatsapp.net', 'Kode itu tidak dikenal');

        $code = app(Linking::class)->issue($this->owner)['code'];
        $this->travel(11)->minutes();
        $this->deliver($this->event("LAIS {$code}", id: 'M2'));
        $this->assertNull($this->owner->refresh()->whatsapp_linked_at);

        // The number already belongs to someone else.
        $other = Business::factory()->create()->user;
        $other->forceFill(['whatsapp' => '6281234567890', 'whatsapp_linked_at' => now()])->save();
        $code = app(Linking::class)->issue($this->owner)['code'];
        $this->deliver($this->event("LAIS {$code}", id: 'M3'));
        $this->assertNull($this->owner->refresh()->whatsapp_linked_at);
        $this->assertSent('6281234567890@s.whatsapp.net', 'sudah terhubung ke pengguna Lais lain');
    }

    public function test_an_unknown_number_hears_from_the_bot_once_a_day(): void
    {
        $this->deliver($this->event('halo', id: 'A'));
        $this->deliver($this->event('halo lagi', id: 'B'));

        Http::assertSentCount(1);
        $this->assertSent('6281234567890@s.whatsapp.net', 'hubungkan dulu akun Lais kamu');

        $this->travel(25)->hours();
        $this->deliver($this->event('halo besok', id: 'C'));
        Http::assertSentCount(2);
    }

    public function test_the_owner_unlinks_and_switches_the_summary(): void
    {
        $this->link();
        $this->actingAs($this->owner);

        $this->patch(route('whatsapp.summary'), ['daily_summary' => false]);
        $this->assertFalse($this->owner->currentBusiness()->refresh()->daily_summary);

        $this->delete(route('whatsapp.unlink'));
        $this->assertNull($this->owner->refresh()->whatsapp);
        $this->assertNull($this->owner->whatsapp_linked_at);
    }

    public function test_an_owner_records_a_sale_by_chat_and_saves_it_with_1(): void
    {
        $this->link();
        $this->catalog();
        $this->model(['isi_transaksi', ['jenis' => 'penjualan', 'metode_bayar' => 'non_tunai', 'baris' => [['item' => 'nasgor', 'qty' => 3], ['item' => 'aqua', 'qty' => 1]]]]);

        $this->deliver($this->event('jual 3 nasgor 1 aqua qris', id: 'S1'));
        $this->assertSent('6281234567890@s.whatsapp.net', "*Penjualan*\n• 3 porsi Nasi Goreng × Rp15.000 = Rp45.000\n• 1 botol Air Mineral = Rp4.000\nTotal: *Rp49.000*");
        $this->assertSent('6281234567890@s.whatsapp.net', 'Balas *1* untuk simpan, *2* untuk batal.');
        $this->assertSame(0, Transaction::query()->where('type', 'penjualan')->count());

        // "1" saves the draft without asking the model again.
        $this->deliver($this->event('1', id: 'S2'));
        $sale = Transaction::query()->where('type', 'penjualan')->sole();
        $this->assertSame([TransactionSource::Wa, 49000], [$sale->source, $sale->total]);
        $this->assertSent('6281234567890@s.whatsapp.net', 'Penjualan tersimpan.');
        $this->assertSent('6281234567890@s.whatsapp.net', route('transactions.show', $sale));
        $this->assertSame(1, $this->modelCalls());

        $this->deliver($this->event('1', id: 'S3'));
        $this->assertSent('6281234567890@s.whatsapp.net', 'Belum ada catatan yang menunggu');
    }

    public function test_where_to_find_something_comes_back_with_the_page_address(): void
    {
        $this->link();
        $this->model(['jelaskan', ['topik' => 'daftar_akun']], ['artikan', ['istilah' => 'cogs']]);

        $this->deliver($this->event('di mana nyatet COA?', id: 'G1'));
        $this->assertSent('6281234567890@s.whatsapp.net', 'Daftar akun (COA) ada di menu Laporan');
        $this->assertSent('6281234567890@s.whatsapp.net', 'Buka Daftar akun: '.route('reports.chart'));

        $this->deliver($this->event('apa itu hpp', id: 'G2'));
        $this->assertSent('6281234567890@s.whatsapp.net', 'HPP: modal bahan dan barang dari produk yang terjual.');
        $this->assertSent('6281234567890@s.whatsapp.net', 'Buka Laba rugi: '.route('reports.profit-loss'));
    }

    public function test_2_drops_the_draft_and_a_draft_lasts_15_minutes(): void
    {
        $this->link();
        $this->catalog();
        $sale = ['isi_transaksi', ['jenis' => 'penjualan', 'metode_bayar' => 'tunai', 'baris' => [['item' => 'aqua', 'qty' => 2]]]];
        $this->model($sale, $sale);

        $this->deliver($this->event('jual 2 aqua', id: 'D1'));
        $this->deliver($this->event('2', id: 'D2'));
        $this->assertSent('6281234567890@s.whatsapp.net', 'Oke, dibatalkan. Tidak ada yang disimpan.');

        $this->deliver($this->event('jual 2 aqua', id: 'D3'));
        $this->travel(16)->minutes();
        $this->deliver($this->event('1', id: 'D4'));
        $this->assertSent('6281234567890@s.whatsapp.net', 'Belum ada catatan yang menunggu');
        $this->assertSame(0, Transaction::query()->where('type', 'penjualan')->count());
    }

    public function test_the_owner_hears_back_when_the_model_is_down_and_the_retry_starts_clean(): void
    {
        $this->link();
        $this->catalog();
        Http::fake(['9router.test/*' => Http::sequence()->push('upstream error', 502)->push(['choices' => [['message' => [
            'role' => 'assistant',
            'tool_calls' => [['id' => 'c', 'type' => 'function', 'function' => ['name' => 'isi_transaksi', 'arguments' => json_encode(['jenis' => 'penjualan', 'metode_bayar' => 'tunai', 'baris' => [['item' => 'aqua', 'qty' => 2]]])]]],
        ], 'finish_reason' => 'tool_calls']]])]);

        $this->deliver($this->event('jual 5 nasgor qris', id: 'E1'));
        $this->assertSent('6281234567890@s.whatsapp.net', 'AI-nya sedang sibuk');

        // The failed message is not part of the next one's history.
        $this->deliver($this->event('jual 2 aqua tunai', id: 'E2'));
        $last = Http::recorded(fn (Request $request) => str_starts_with($request->url(), 'http://9router.test'))->last()[0];
        $this->assertStringNotContainsString('jual 5 nasgor qris', json_encode($last['messages']));
    }

    public function test_orders_run_from_the_chat_and_the_owner_hears_of_new_ones(): void
    {
        $this->link();
        $this->catalog();
        $business = $this->owner->currentBusiness();
        $water = Item::query()->where('business_id', $business->id)->where('name', 'Air Mineral')->sole();

        $this->post(route('shop.store', $business->slug), [
            'customer_name' => 'Bu Sari', 'customer_phone' => '081299990000', 'fulfillment' => 'ambil',
            'lines' => [['item_id' => $water->id, 'qty' => 5]],
        ])->assertSessionHasNoErrors();
        $this->assertSent('6281234567890', "*Pesanan baru A001* dari Bu Sari\n• 5 Air Mineral\nTotal: *Rp20.000*");
        $this->assertSent('6281234567890', 'Balas *terima A001* untuk terima pesanan, *tolak A001* untuk tolak.');

        $this->deliver($this->event('terima a001', id: 'O1'));
        $this->assertSent('6281234567890@s.whatsapp.net', 'Pesanan A001 diterima dan sedang diproses.');
        $this->assertSent('6281234567890@s.whatsapp.net', 'https://wa.me/6281299990000?text=');

        $this->deliver($this->event('A001 dp 8rb qris', id: 'O2'));
        $this->assertSent('6281234567890@s.whatsapp.net', 'Pembayaran Rp8.000 (Non-tunai) untuk A001 dicatat. Sisa Rp12.000.');
        // Recorded from the bot, so it says so in the history.
        $this->assertSame(TransactionSource::Wa, Order::query()->sole()->transactions()->sole()->source);

        $this->deliver($this->event('A001 selesai', id: 'O3'));
        $this->assertSent('6281234567890@s.whatsapp.net', 'Pesanan A001 selesai dan tercatat sebagai penjualan. Sisa Rp12.000 jadi piutang Bu Sari.');

        $this->deliver($this->event('A001 lunas', id: 'O4'));
        $order = Order::query()->sole();
        $this->assertSame([OrderStatus::Selesai, 20000], [$order->status, app(Orders::class)->paid($order)]);
        $this->assertSame(0, $this->modelCalls());

        // Refusals come back as the web's own messages; another business's order is out of reach.
        $this->deliver($this->event('terima A001', id: 'O5'));
        $this->assertSent('6281234567890@s.whatsapp.net', 'Pesanan ini sudah diterima atau ditolak.');
        $this->deliver($this->event('terima A777', id: 'O6'));
        $this->assertSent('6281234567890@s.whatsapp.net', 'Pesanan A777 tidak ditemukan.');
    }

    public function test_the_news_for_the_customer_follows_the_order(): void
    {
        $this->catalog();
        $business = $this->owner->currentBusiness();
        $water = Item::query()->where('business_id', $business->id)->where('name', 'Air Mineral')->sole();
        $order = app(Orders::class)->place($business, [
            'customer_name' => 'Bu Sari', 'customer_phone' => '081299990000', 'fulfillment' => 'ambil',
            'lines' => [['item_id' => $water->id, 'qty' => 5]],
        ], TransactionSource::Web);
        $nota = route('shop.order', $order->token);

        $this->assertSame("Halo Bu Sari, pesanan A001 di Warung Ani sedang kami siapkan. Totalnya Rp20.000. Sisa yang perlu dibayar Rp12.000.\n\nStatus dan nota: {$nota}", OrderMessages::status($order, 8000));

        $order->forceFill(['status' => OrderStatus::Siap, 'fulfillment' => 'antar'])->save();
        $this->assertStringContainsString('sedang diantar ke alamat Anda.', OrderMessages::status($order, 0));

        $order->forceFill(['status' => OrderStatus::Selesai])->save();
        $this->assertStringContainsString('Pembayarannya sudah lunas.', OrderMessages::status($order, 20000));

        // Called off after a deposit: the deposit is voided, so paid() is 0, yet the news says it comes back.
        $order->forceFill(['status' => OrderStatus::Siap])->save();
        app(Orders::class)->recordPayment($order, 8000, PaymentMethod::Tunai);
        app(Orders::class)->cancel($order->refresh(), 'Bahan habis');
        $this->assertStringContainsString('tidak bisa kami proses (Bahan habis). Uang muka Rp8.000 kami kembalikan.', OrderMessages::status($order->refresh(), app(Orders::class)->paid($order)));
    }

    public function test_why_profit_and_cash_differ_is_answered_from_the_books(): void
    {
        $this->link();
        // The catalog opened last month, so this month only holds what follows.
        $this->travelTo(CarbonImmutable::parse('2026-10-25 10:00'));
        $this->catalog();
        $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00'));
        $business = $this->owner->currentBusiness();
        $water = Item::query()->where('business_id', $business->id)->where('name', 'Air Mineral')->sole();
        $record = fn (array $data) => app(TransactionService::class)->record($business, $data);
        $record(['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 100000]);
        $record(['type' => 'penjualan', 'method' => 'kredit', 'contact_name' => 'Bu Sari', 'lines' => [['item_id' => $water->id, 'qty' => 8, 'price' => 4000]]]);
        $record(['type' => 'modal', 'kind' => 'prive', 'method' => 'tunai', 'amount' => 50000]);
        $this->deliver($this->event('kenapa laba naik tapi kas turun?', id: 'R1'));
        // Answered from the journal without asking the model.
        $this->assertSame(0, $this->modelCalls());

        $this->assertSent('6281234567890@s.whatsapp.net', implode("\n", [
            'Bulan ini laba bersih Rp12.000, sedangkan uang usaha naik Rp50.000.',
            'Bedanya karena:',
            '• Stok terjual tanpa belanja lagi: +Rp20.000',
            '• Penjualan yang belum dibayar pelanggan: −Rp32.000',
            '• Setoran modal dari uang pribadi: +Rp100.000',
            '• Diambil untuk pribadi: −Rp50.000',
        ]));
    }

    public function test_the_evening_summary_goes_to_owners_who_want_it(): void
    {
        $this->link();
        $this->catalog();
        $business = $this->owner->currentBusiness();
        $water = Item::query()->where('business_id', $business->id)->where('name', 'Air Mineral')->sole();
        $water->update(['min_stock' => 6]);
        app(TransactionService::class)->record($business, ['type' => 'penjualan', 'method' => 'tunai', 'lines' => [['item_id' => $water->id, 'qty' => 24, 'price' => 4000]]]);
        $quiet = Business::factory()->create(['daily_summary' => false])->user;
        $quiet->forceFill(['whatsapp' => '6281200000001', 'whatsapp_linked_at' => now()])->save();
        Business::factory()->create();

        $this->artisan('lais:ringkasan')->expectsOutput('Ringkasan dikirim ke 1 usaha.')->assertSuccessful();

        $this->assertSent('6281234567890', '*Ringkasan Warung Ani, ');
        $this->assertSent('6281234567890', 'Penjualan: Rp96.000 (1 transaksi)');
        $this->assertSent('6281234567890', "Stok mau habis:\n• Air Mineral sudah habis");
        Http::assertNotSent(fn (Request $request) => $request['phone'] === '6281200000001');

        // On demand for a demo, whatever the switch says.
        $this->artisan('lais:ringkasan', ['--usaha' => $quiet->currentBusiness()->id])->assertSuccessful();
        $this->assertSent('6281200000001', '*Ringkasan ');
    }

    public function test_a_receipt_photo_becomes_a_purchase_with_the_photo_as_proof(): void
    {
        Storage::fake('local');
        $this->link();
        $this->catalog();
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

        $this->deliver(['event' => 'message', 'payload' => [
            'id' => 'P1', 'chat_id' => '6281234567890@s.whatsapp.net', 'from' => '6281234567890@s.whatsapp.net',
            'is_from_me' => false, 'body' => '', 'image' => ['url' => 'https://mmg.whatsapp.net/x', 'mime_type' => 'image/png'],
        ]]);
        $this->assertSent('6281234567890@s.whatsapp.net', "*Belanja*\n• 5 kg Beras × Rp14.000 = Rp70.000");
        Http::assertSent(fn (Request $request) => str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?: '', '/message/P1/download'));

        $this->deliver($this->event('1', id: 'P2'));
        $purchase = Transaction::query()->where('type', 'pembelian')->sole();
        $this->assertSame([TransactionSource::Wa, 130000], [$purchase->source, $purchase->total]);
        Storage::disk('local')->assertExists($purchase->proof_path);
    }

    private function catalog(): void
    {
        app(Catalog::class)->createMany($this->owner->currentBusiness(), [
            ['name' => 'Beras', 'kind' => 'bahan', 'unit' => 'kg', 'opening_stock' => 10, 'opening_cost' => 14000],
            ['name' => 'Telur', 'kind' => 'bahan', 'unit' => 'butir', 'opening_stock' => 30, 'opening_cost' => 2000],
            ['name' => 'Air Mineral', 'kind' => 'produk', 'unit' => 'botol', 'price' => 4000, 'aliases' => ['aqua'], 'opening_stock' => 24, 'opening_cost' => 2500],
            ['name' => 'Nasi Goreng', 'kind' => 'komposisi', 'unit' => 'porsi', 'price' => 15000, 'aliases' => ['nasgor'], 'recipe' => [['name' => 'Beras', 'qty' => 0.15], ['name' => 'Telur', 'qty' => 1]]],
        ]);
    }

    /**
     * 9Router's answers, one forced tool call per turn.
     *
     * @param  array{0: string, 1: array<string, mixed>}  ...$turns
     */
    private function model(array ...$turns): void
    {
        $sequence = Http::sequence();

        foreach ($turns as [$name, $args]) {
            $sequence->push(['choices' => [['message' => [
                'role' => 'assistant',
                'tool_calls' => [['id' => 'call_1', 'type' => 'function', 'function' => ['name' => $name, 'arguments' => json_encode($args)]]],
            ], 'finish_reason' => 'tool_calls']]]);
        }

        Http::fake(['9router.test/*' => $sequence]);
    }

    private function modelCalls(): int
    {
        return Http::recorded(fn (Request $request) => str_starts_with($request->url(), 'http://9router.test'))->count();
    }

    private function link(): void
    {
        $this->owner->forceFill(['whatsapp' => '6281234567890', 'whatsapp_linked_at' => now()])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function event(string $body, string $id = 'M1', string $from = '6281234567890@s.whatsapp.net', ?string $lid = null, bool $fromMe = false, ?string $chat = null): array
    {
        return ['event' => 'message', 'device_id' => '6281100000000@s.whatsapp.net', 'payload' => array_filter([
            'id' => $id,
            'chat_id' => $chat ?? $from,
            'from' => $from,
            'from_lid' => $lid,
            'is_from_me' => $fromMe,
            'body' => $body,
            'timestamp' => now()->toRfc3339String(),
        ], fn (mixed $value) => $value !== null)];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return TestResponse<Response>
     */
    private function deliver(array $event): TestResponse
    {
        $body = (string) json_encode($event);

        return $this->call('POST', route('webhooks.gowa'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, self::SECRET),
        ], $body);
    }

    private function assertSent(string $to, string $text): void
    {
        Http::assertSent(fn (Request $request) => $request->url() === 'http://gowa.test/send/message'
            && $request->hasHeader('X-Device-Id', 'lais')
            && $request['phone'] === $to
            && str_contains((string) $request['message'], $text));
    }
}
