<?php

namespace Tests\Feature;

use App\Enums\TransactionSource;
use App\Models\Activity;
use App\Models\Business;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WaMessage;
use App\Services\Catalog;
use App\Services\WhatsApp\Linking;
use App\Services\WhatsApp\Telegram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\TestCase;

/**
 * The bot on Telegram. What it says is WhatsAppTest's job; here is only what
 * differs: how messages arrive, how the owner links, and how replies leave.
 */
class TelegramTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = '123456:rahasia-token-bot';

    private const API = 'https://api.telegram.org/bot'.self::TOKEN;

    private const CHAT = 555001;

    private User $owner;

    /** @var list<list<array<string, mixed>>> what getUpdates answers, one batch per call */
    private array $batches = [];

    /** How Telegram answers for now: normal, refused, or unreachable. */
    private string $telegram = 'normal';

    private int $updateId = 100;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.llm.url' => 'http://9router.test/v1',
            'services.llm.key' => 'test-key',
            'services.llm.model' => 'test-model',
            'services.bot.channel' => 'telegram',
            'services.telegram.token' => self::TOKEN,
            'services.telegram.username' => 'lais_bot',
        ]);
        // Other hosts fall through to later fakes.
        Http::fake(fn (Request $request) => match (true) {
            ! str_starts_with($request->url(), 'https://api.telegram.org') => null,
            $this->telegram === 'unreachable' => Http::failedConnection('cURL error 7 for '.$request->url()),
            $this->telegram === 'refused' => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401),
            str_ends_with($request->url(), '/getUpdates') => Http::response(['ok' => true, 'result' => array_shift($this->batches) ?? []]),
            str_ends_with($request->url(), '/getFile') => Http::response(['ok' => true, 'result' => ['file_id' => 'BESAR', 'file_path' => 'photos/nota.png']]),
            str_contains($request->url(), '/file/bot') => Http::response((string) file_get_contents(base_path('tests/Fixtures/nota-belanja.png'))),
            default => Http::response(['ok' => true, 'result' => ['message_id' => 7]]),
        });
        $this->owner = Business::factory()->create(['name' => 'Warung Ani'])->user;
    }

    public function test_the_owner_links_through_the_start_link_and_can_unlink(): void
    {
        $this->actingAs($this->owner)->post(route('whatsapp.code'))->assertRedirect();
        $code = app(Linking::class)->pending($this->owner)['code'];

        $this->get(route('whatsapp.edit'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('channel', 'Telegram')
            ->where('contact', '@lais_bot')
            ->where('pending.url', "https://t.me/lais_bot?start={$code}"));

        $this->receive("/start {$code}");

        $this->owner->refresh();
        $this->assertSame([(string) self::CHAT, null], [$this->owner->telegram_chat_id, $this->owner->whatsapp]);
        $this->assertSame((string) self::CHAT, $this->owner->whatsappAddress());
        $this->assertSent('Bot terhubung ke Warung Ani');

        $this->receive('/help');
        $this->assertSent('Ceritakan yang mau dicatat');
        $this->assertSame(0, $this->modelCalls());

        $this->delete(route('whatsapp.unlink'));
        $this->assertNull($this->owner->refresh()->telegram_chat_id);
        $this->assertNull($this->owner->whatsappAddress());
    }

    public function test_a_guessed_code_stops_working_after_five_tries(): void
    {
        $code = app(Linking::class)->issue($this->owner)['code'];
        $wrong = $code === '100000' ? '100001' : '100000';

        foreach (range(1, 5) as $try) {
            $this->receive("/start {$wrong}");
        }
        $this->assertSent('Kode itu tidak dikenal');

        $this->receive("/start {$code}");
        $this->assertNull($this->owner->refresh()->whatsapp_linked_at);
        $this->assertSent('Terlalu banyak percobaan');

        // Someone else's tries are their own.
        $this->receive("/start {$code}", chat: 555002);
        $this->assertSame('555002', $this->owner->refresh()->telegram_chat_id);
    }

    public function test_a_chat_already_linked_to_someone_else_links_nothing(): void
    {
        Business::factory()->create()->user->forceFill(['telegram_chat_id' => (string) self::CHAT, 'whatsapp_linked_at' => now()])->save();
        $code = app(Linking::class)->issue($this->owner)['code'];

        $this->receive("/start {$code}");

        $this->assertNull($this->owner->refresh()->whatsapp_linked_at);
        $this->assertSent('sudah terhubung ke pengguna Lais lain');
    }

    public function test_only_a_persons_own_chat_is_kept_and_each_update_only_once(): void
    {
        $update = $this->update('halo');
        $telegram = app(Telegram::class);

        $telegram->receive($update);
        $telegram->receive($update);
        $telegram->receive($this->update('di grup', type: 'group'));
        $telegram->receive(['update_id' => 900, 'message' => ['chat' => ['id' => self::CHAT, 'type' => 'private'], 'sticker' => ['file_id' => 'S']]]);
        $telegram->receive(['update_id' => 901]);

        $this->assertSame(1, WaMessage::query()->where('direction', 'in')->count());
        // A stranger hears once, however many times the update comes.
        $this->assertSent('belum terhubung ke Lais');
        Http::assertSentCount(1);
    }

    public function test_an_owner_records_a_sale_and_the_books_name_telegram(): void
    {
        $this->link();
        $this->catalog();
        $this->model(['isi_transaksi', ['jenis' => 'penjualan', 'metode_bayar' => 'tunai', 'baris' => [['item' => 'aqua', 'qty' => 2]]]]);

        $this->receive('jual 2 aqua tunai');
        $this->assertSent('<b>Penjualan</b>');
        $this->assertSent('Balas <b>1</b> untuk simpan, <b>2</b> untuk batal.');

        $this->receive('1');
        $sale = Transaction::query()->where('type', 'penjualan')->sole();
        $this->assertSame([TransactionSource::Telegram, 8000], [$sale->source, $sale->total]);

        $log = Activity::query()->where('subject_type', $sale->getMorphClass())->where('subject_id', $sale->id)->sole();
        $this->assertSame(['tg', $this->owner->id], [$log->getProperty('source'), $log->causer_id]);
    }

    public function test_the_owner_hears_of_new_orders_and_the_evening_summary_in_the_chat(): void
    {
        $this->link();
        $this->catalog();
        $business = $this->owner->currentBusiness();
        $water = Item::query()->where('business_id', $business->id)->where('name', 'Air Mineral')->sole();

        $this->post(route('shop.store', $business->slug), [
            'customer_name' => 'Bu Sari', 'customer_phone' => '081299990000', 'fulfillment' => 'ambil',
            'lines' => [['item_id' => $water->id, 'qty' => 5]],
        ])->assertSessionHasNoErrors();
        $this->assertSent('<b>Pesanan baru A001</b> dari Bu Sari');

        $this->artisan('lais:ringkasan')->assertSuccessful();
        $this->assertSent('<b>Ringkasan Warung Ani, ');
    }

    public function test_the_largest_size_of_a_photo_becomes_the_proof(): void
    {
        Storage::fake('local');
        $this->link();
        $this->catalog();
        $this->model(
            ['tulis_nota', ['bukan_nota' => false, 'toko' => 'Toko Makmur', 'tanggal' => '2026-10-27 09:14', 'cara_bayar' => 'tunai', 'baris' => [['nama' => 'Telur', 'jumlah' => 30, 'total' => 60000]]]],
            ['isi_transaksi', ['jenis' => 'pembelian', 'jenis_biaya' => 'barang', 'metode_bayar' => 'tunai', 'kontak' => 'Toko Makmur', 'tanggal' => '2026-10-27 09:14', 'baris' => [['item' => 'Telur', 'qty' => 30, 'total' => 60000]]]],
        );

        app(Telegram::class)->receive([
            'update_id' => 700,
            'message' => ['chat' => ['id' => self::CHAT, 'type' => 'private'], 'photo' => [
                ['file_id' => 'KECIL', 'width' => 90, 'height' => 90],
                ['file_id' => 'BESAR', 'width' => 1280, 'height' => 1280],
            ]],
        ]);
        Http::assertSent(fn (Request $request) => $request->url() === self::API.'/getFile' && $request['file_id'] === 'BESAR');
        $this->assertSent('<b>Belanja</b>');

        $this->receive('1');
        Storage::disk('local')->assertExists(Transaction::query()->where('type', 'pembelian')->sole()->proof_path);
    }

    public function test_replies_are_safe_html_and_never_too_long(): void
    {
        $this->assertSame('Bu &lt;Ani&gt; &amp; <b>Rp5.000</b> 2*3', Telegram::html('Bu <Ani> & *Rp5.000* 2*3'));

        app(Telegram::class)->send('555', str_repeat('a', 5000));

        Http::assertSent(fn (Request $request) => mb_strlen((string) $request['text']) === 4000);
    }

    public function test_a_failure_never_quotes_the_token(): void
    {
        foreach (['unreachable', 'refused'] as $state) {
            $this->telegram = $state;

            try {
                app(Telegram::class)->send('555', 'halo');
                $this->fail('Kegagalan harus dilaporkan.');
            } catch (RuntimeException $exception) {
                $this->assertStringNotContainsString(self::TOKEN, $exception->getMessage());
                $this->assertNull($exception->getPrevious());
            }
        }

        $this->assertStringContainsString('Unauthorized', $exception->getMessage());
    }

    public function test_the_poller_takes_what_waits_and_confirms_it(): void
    {
        $this->batches = [[$this->update('satu'), $this->update('dua')]];

        $this->artisan('lais:telegram', ['--once' => true])->assertSuccessful();

        $this->assertSame(['satu', 'dua'], WaMessage::query()->where('direction', 'in')->orderBy('id')->pluck('body')->all());
        // The next call asks past the last update, which is how Telegram learns it arrived.
        Http::assertSent(fn (Request $request) => $request->url() === self::API.'/getUpdates' && $request['offset'] === $this->updateId + 1);
    }

    public function test_the_poller_survives_telegram_being_away(): void
    {
        $this->telegram = 'unreachable';

        $this->artisan('lais:telegram', ['--once' => true])->assertSuccessful();

        $this->assertSame(0, WaMessage::query()->count());

        // Without a token there is nothing to wait for.
        config(['services.telegram.token' => '']);
        $this->artisan('lais:telegram')->assertFailed();
    }

    public function test_a_receipt_keeps_its_photo_while_its_goods_are_listed_from_the_chat(): void
    {
        Storage::fake('local');
        $this->link();
        $this->catalog();
        $this->model(
            ['tulis_nota', ['bukan_nota' => false, 'cara_bayar' => 'tunai', 'baris' => [['nama' => 'Telur', 'jumlah' => 30, 'total' => 60000], ['nama' => 'Frestea', 'jumlah' => 2, 'total' => 12000]]]],
            ['isi_transaksi', ['jenis' => 'pembelian', 'jenis_biaya' => 'barang', 'metode_bayar' => 'tunai', 'baris' => [['item' => 'Telur', 'qty' => 30, 'total' => 60000], ['item' => 'Frestea', 'qty' => 2, 'total' => 12000]]]],
            ['tambah_ke_daftar', ['barang' => [['nama' => 'Frestea', 'jenis' => 'bahan', 'satuan' => 'botol']]]],
        );

        app(Telegram::class)->receive([
            'update_id' => 701,
            'message' => ['chat' => ['id' => self::CHAT, 'type' => 'private'], 'photo' => [['file_id' => 'BESAR', 'width' => 1280, 'height' => 1280]]],
        ]);
        $this->assertSent('Daftarkan barang yang belum ada');

        // A draft that still misses goods is not saved by "1".
        $this->receive('1');
        $this->assertSent('Belum bisa disimpan');
        $this->assertSame(0, Transaction::query()->where('type', 'pembelian')->count());

        $this->receive('daftarkan yang kurang');
        $this->assertSent('Tambah ke daftar produk');

        $this->receive('1');
        $this->assertSent('1 barang masuk daftar produk');

        $this->receive('1');
        $record = Transaction::query()->where('type', 'pembelian')->sole();
        $this->assertSame(72000, $record->total);
        Storage::disk('local')->assertExists($record->proof_path);
    }

    private function catalog(): void
    {
        app(Catalog::class)->createMany($this->owner->currentBusiness(), [
            ['name' => 'Telur', 'kind' => 'bahan', 'unit' => 'butir', 'opening_stock' => 30, 'opening_cost' => 2000],
            ['name' => 'Air Mineral', 'kind' => 'produk', 'unit' => 'botol', 'price' => 4000, 'aliases' => ['aqua'], 'opening_stock' => 24, 'opening_cost' => 2500],
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
        $this->owner->forceFill(['telegram_chat_id' => (string) self::CHAT, 'whatsapp_linked_at' => now()])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function update(string $text, int $chat = self::CHAT, string $type = 'private'): array
    {
        return ['update_id' => ++$this->updateId, 'message' => ['chat' => ['id' => $chat, 'type' => $type], 'text' => $text]];
    }

    private function receive(string $text, int $chat = self::CHAT): void
    {
        app(Telegram::class)->receive($this->update($text, $chat));
    }

    private function assertSent(string $text): void
    {
        Http::assertSent(fn (Request $request) => $request->url() === self::API.'/sendMessage'
            && $request['parse_mode'] === 'HTML'
            && str_contains((string) $request['text'], $text));
    }
}
