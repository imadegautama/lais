<?php

namespace Tests\Feature;

use App\Enums\TransactionSource;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Item;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\Onboarding;
use App\Services\Orders;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProofTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-11-18 14:00'));
        $this->business = $this->openBooks();
        $this->actingAs($this->business->user);
    }

    public function test_a_record_keeps_its_proof_for_the_owner_only(): void
    {
        $this->post(route('transactions.store'), $this->expense(['proof' => UploadedFile::fake()->image('tf.png')]))->assertSessionHasNoErrors();
        $transaction = Transaction::query()->latest('id')->firstOrFail();

        Storage::disk('local')->assertExists($transaction->proof_path);
        $this->assertStringStartsWith("bukti/{$this->business->id}/", $transaction->proof_path);
        $this->get(route('transactions.proof', $transaction))->assertOk()->assertHeader('Cache-Control', 'max-age=86400, private');
        $this->get(route('transactions.show', $transaction))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('transaction.proofUrl', route('transactions.proof', $transaction)));

        // Another owner gets nothing, a guest is sent to log in, and a PDF is no photo.
        $this->actingAs(Business::factory()->create()->user)->get(route('transactions.proof', $transaction))->assertNotFound();
        auth()->logout();
        $this->get(route('transactions.proof', $transaction))->assertRedirect(route('login'));
        $this->actingAs($this->business->user)
            ->post(route('transactions.store'), $this->expense(['proof' => UploadedFile::fake()->create('tf.pdf', 10, 'application/pdf')]))
            ->assertSessionHasErrors('proof');
    }

    public function test_proofs_follow_the_private_disk_chosen_for_production(): void
    {
        config(['filesystems.proof_disk' => 'r2-private']);
        Storage::fake('r2-private');

        $this->post(route('transactions.store'), $this->expense(['proof' => UploadedFile::fake()->image('tf.png')]))->assertSessionHasNoErrors();
        $transaction = Transaction::query()->latest('id')->firstOrFail();

        Storage::disk('r2-private')->assertExists($transaction->proof_path);
        Storage::disk('local')->assertMissing($transaction->proof_path);
        $this->get(route('transactions.proof', $transaction))->assertOk();
    }

    public function test_ubah_carries_the_proof_over_unless_it_is_replaced_or_removed(): void
    {
        $this->post(route('transactions.store'), $this->expense(['proof' => UploadedFile::fake()->image('tf.png')]));
        $first = Transaction::query()->latest('id')->firstOrFail();

        $this->put(route('transactions.update', $first), $this->expense(['amount' => 60000]));
        $second = Transaction::query()->latest('id')->firstOrFail();
        $this->assertSame($first->proof_path, $second->proof_path);

        $this->put(route('transactions.update', $second), $this->expense(['proof' => UploadedFile::fake()->image('baru.png')]));
        $third = Transaction::query()->latest('id')->firstOrFail();
        $this->assertNotSame($second->proof_path, $third->proof_path);

        $this->put(route('transactions.update', $third), $this->expense(['remove_proof' => true]));
        $this->assertNull(Transaction::query()->latest('id')->firstOrFail()->proof_path);
        // The old versions keep their files.
        Storage::disk('local')->assertExists([$first->proof_path, $third->proof_path]);
    }

    public function test_payments_on_orders_and_debts_keep_their_proof(): void
    {
        $water = Item::query()->where('name', 'Air Mineral')->sole();
        $orders = app(Orders::class);
        $order = $orders->place($this->business, [
            'customer_name' => 'Bu Sari', 'customer_phone' => '081234567890', 'fulfillment' => 'ambil',
            'lines' => [['item_id' => $water->id, 'qty' => 2]],
        ], TransactionSource::Web);

        $this->post(route('orders.pay', $order->code), ['amount' => 3000, 'method' => 'non_tunai', 'proof' => UploadedFile::fake()->image('tf.jpg')])
            ->assertSessionHasNoErrors();
        $this->assertNotNull(Order::query()->sole()->transactions()->sole()->proof_path);
        $this->get(route('orders.show', $order->code))->assertInertia(fn (AssertableInertia $page) => $page->where('order.payments.0.hasProof', true));

        $supplier = Contact::query()->where('name', 'Toko Sumber')->sole();
        $this->post(route('debts.pay', $supplier), ['amount' => 50000, 'method' => 'non_tunai', 'proof' => UploadedFile::fake()->image('tf.jpg')])
            ->assertSessionHasNoErrors();
        $this->get(route('debts.show', $supplier))->assertInertia(fn (AssertableInertia $page) => $page->where('payments.0.hasProof', true));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function expense(array $overrides = []): array
    {
        return $overrides + ['type' => 'biaya', 'kind' => 'listrik_air', 'method' => 'non_tunai', 'amount' => 50000];
    }

    private function openBooks(): Business
    {
        $business = Business::factory()->create();
        [$cashBox, $rekening] = $business->moneyAccounts()->orderBy('id')->get()->all();

        app(Onboarding::class)->complete($business, [
            'name' => 'Warung Ani',
            'started_on' => '2026-10-01',
            'accounts' => [['id' => $cashBox->id, 'name' => 'Kas tunai', 'balance' => 500000], ['id' => $rekening->id, 'name' => 'BCA', 'balance' => 1000000]],
            'equipment' => [],
            'products' => [['name' => 'Air Mineral', 'kind' => 'produk', 'unit' => 'botol', 'price' => 4000, 'min_stock' => 20, 'opening_stock' => 100, 'opening_cost' => 2500]],
            'debts' => [['name' => 'Toko Sumber', 'amount' => 300000]],
        ]);

        return $business->refresh();
    }
}
