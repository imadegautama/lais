<?php

namespace Tests\Feature;

use App\Enums\ContactType;
use App\Enums\OrderFulfillment;
use App\Enums\OrderStatus;
use App\Enums\TransactionSource;
use App\Models\Activity;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Item;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\BusinessFacts;
use App\Services\Catalog;
use App\Services\Reports;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Item $tea;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();
        $this->tea = app(Catalog::class)->createMany($this->business, [
            ['name' => 'Es Teh', 'kind' => 'produk', 'unit' => 'gelas', 'price' => 5000, 'opening_stock' => 100, 'opening_cost' => 2000],
        ])[0];
    }

    public function test_the_list_shows_one_side_with_history_and_balance_and_keeps_it_while_sorting(): void
    {
        $sari = $this->contact('Bu Sari', '6281234567890');
        $this->contact('Pak Budi');
        $this->contact('Toko Sumber', type: ContactType::Supplier);
        $this->sale($sari, 'kredit', 2);
        $other = Business::factory()->create()->contacts()->create(['type' => ContactType::Pelanggan, 'name' => 'Rahasia']);

        $this->actingAs($this->business->user)->get(route('contacts.index', ['urut' => '-transaksi']))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('contacts/index')
                ->where('tab', 'pelanggan')
                ->where('counts', ['pelanggan' => 2, 'supplier' => 1])
                ->has('contacts.data', 2)
                ->where('contacts.data.0.name', 'Bu Sari')
                ->where('contacts.data.0.transactions', 1)
                ->where('contacts.data.0.balance', 10000)
                ->where('contacts.query.jenis', ['pelanggan']));

        $this->get(route('contacts.index', ['jenis' => 'supplier']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('tab', 'supplier')->has('contacts.data', 1)->where('contacts.data.0.name', 'Toko Sumber'));
        $this->get(route('contacts.index', ['cari' => '0812345']))->assertInertia(fn (AssertableInertia $page) => $page->has('contacts.data', 0));
        $this->get(route('contacts.index', ['cari' => '6281234']))->assertInertia(fn (AssertableInertia $page) => $page->has('contacts.data', 1));
        $this->get(route('contacts.show', $other))->assertNotFound();
    }

    public function test_a_contact_is_added_and_corrected_with_a_clean_number_and_a_unique_name(): void
    {
        $this->actingAs($this->business->user);

        $this->post(route('contacts.store'), ['type' => 'pelanggan', 'name' => ' Bu Ani ', 'phone' => '0812-3456-7890'])->assertRedirect();
        $contact = Contact::query()->where('name', 'Bu Ani')->sole();
        $this->assertSame('6281234567890', $contact->phone);

        $this->post(route('contacts.store'), ['type' => 'pelanggan', 'name' => 'Bu Ani'])->assertSessionHasErrors(['name' => 'Nama ini sudah ada di daftar kontak.']);
        // The same name on the other side is another party.
        $this->post(route('contacts.store'), ['type' => 'supplier', 'name' => 'Bu Ani'])->assertSessionHasNoErrors();
        $this->post(route('contacts.store'), ['type' => 'pelanggan', 'name' => 'Pak Ujang', 'phone' => '12345'])->assertSessionHasErrors('phone');

        $this->put(route('contacts.update', $contact), ['name' => 'Bu Ani Rahayu', 'phone' => ''])->assertSessionHasNoErrors();
        $this->assertSame(['Bu Ani Rahayu', null], [$contact->fresh()->name, $contact->fresh()->phone]);
        $this->put(route('contacts.update', $contact), ['type' => 'supplier', 'name' => 'Bu Ani Rahayu'])->assertSessionHasErrors('type');

        $other = Business::factory()->create()->contacts()->create(['type' => ContactType::Pelanggan, 'name' => 'Rahasia']);
        $this->put(route('contacts.update', $other), ['name' => 'Diubah'])->assertNotFound();
        $this->delete(route('contacts.destroy', $other))->assertNotFound();
    }

    public function test_a_contact_with_something_open_cannot_be_removed(): void
    {
        $owing = $this->contact('Bu Sari');
        $this->sale($owing, 'kredit', 2);
        $this->actingAs($this->business->user);

        $this->delete(route('contacts.destroy', $owing))->assertRedirect();
        $this->assertSame('Bu Sari', $owing->fresh()->name);

        $waiting = $this->contact('Pak Budi', '6281111111111');
        $this->order($waiting, OrderStatus::Diproses);

        $this->delete(route('contacts.destroy', $waiting));
        $this->assertSame('6281111111111', $waiting->fresh()->phone);
    }

    public function test_a_contact_without_history_is_deleted(): void
    {
        $contact = $this->contact('Salah Ketik');

        $this->actingAs($this->business->user)->delete(route('contacts.destroy', $contact))->assertRedirect(route('contacts.index', ['jenis' => 'pelanggan']));

        $this->assertNull(Contact::query()->find($contact->id));
    }

    public function test_removing_a_contact_with_history_keeps_the_books_and_drops_the_person(): void
    {
        $contact = $this->contact('Bu Sari', '6281234567890');
        $this->sale($contact, 'tunai', 3);
        $order = $this->order($contact, OrderStatus::Selesai);
        $reports = app(Reports::class);
        $before = [$reports->profitAndLoss($this->business, now()->startOfMonth()), $reports->balanceSheet($this->business, now())];

        $this->actingAs($this->business->user)->delete(route('contacts.destroy', $contact))->assertRedirect();

        $this->assertSame($before, [$reports->profitAndLoss($this->business, now()->startOfMonth()), $reports->balanceSheet($this->business, now())]);
        $this->assertSame(1, Transaction::query()->where('contact_id', $contact->id)->count());

        $erased = $contact->fresh();
        $this->assertSame(["Kontak terhapus #{$contact->id}", null], [$erased->name, $erased->phone]);
        $this->assertNotNull($erased->erased_at);
        $this->assertSame([$erased->name, '', null], [$order->fresh()->customer_name, $order->fresh()->customer_phone, $order->fresh()->address]);

        // The public receipt no longer names them, and they are gone from every list.
        $this->get(route('shop.order', $order->token))->assertOk()->assertDontSee('Bu Sari')->assertDontSee('6281234567890');
        $this->assertSame(0, $this->business->contacts()->count());
        $this->get(route('contacts.index'))->assertInertia(fn (AssertableInertia $page) => $page->has('contacts.data', 0));
        $this->get(route('contacts.show', $contact))->assertNotFound();
        $this->assertStringNotContainsString('Bu Sari', app(BusinessFacts::class)->sheet($this->business));
    }

    public function test_the_log_keeps_no_phone_number(): void
    {
        $this->actingAs($this->business->user);
        $this->post(route('contacts.store'), ['type' => 'pelanggan', 'name' => 'Bu Ani', 'phone' => '081234567890']);
        $contact = Contact::query()->where('name', 'Bu Ani')->sole();
        $this->delete(route('contacts.destroy', $contact));

        $this->assertStringNotContainsString('6281234567890', (string) json_encode(Activity::query()->get()->map->properties));
    }

    private function contact(string $name, ?string $phone = null, ContactType $type = ContactType::Pelanggan): Contact
    {
        return $this->business->contacts()->create(['type' => $type, 'name' => $name, 'phone' => $phone]);
    }

    private function sale(Contact $contact, string $method, int $qty): void
    {
        app(TransactionService::class)->record($this->business, [
            'type' => 'penjualan', 'method' => $method, 'contact_id' => $contact->id, 'paid_amount' => 0,
            'lines' => [['item_id' => $this->tea->id, 'qty' => $qty, 'price' => 5000]],
        ]);
    }

    private function order(Contact $contact, OrderStatus $status): Order
    {
        return Order::query()->forceCreate([
            'business_id' => $this->business->id, 'contact_id' => $contact->id, 'code' => 'A'.random_int(100, 999), 'token' => Str::random(32),
            'status' => $status, 'source' => TransactionSource::Web, 'fulfillment' => OrderFulfillment::cases()[0],
            'customer_name' => $contact->name, 'customer_phone' => (string) $contact->phone, 'address' => 'Jl. Melati 3', 'total' => 5000,
        ]);
    }
}
