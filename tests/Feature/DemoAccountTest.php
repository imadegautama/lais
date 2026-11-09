<?php

namespace Tests\Feature;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Business;
use App\Models\User;
use App\Services\DemoAccounts;
use App\Services\Orders;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * "Coba demo": every visitor gets an account of their own with the Warung
 * Bu Rina books, ready in a pool, removed with its business when it runs out.
 */
class DemoAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Two days of history keep each account quick to make.
        config(['services.demo.days' => 2, 'services.demo.pool' => 2]);
        Storage::fake(Media::diskName());
    }

    public function test_the_pool_keeps_accounts_ready_with_their_own_books(): void
    {
        $this->artisan('lais:demo')->assertSuccessful();
        $this->artisan('lais:demo')->assertSuccessful();

        $ready = User::query()->where('is_demo', true)->whereNull('demo_until')->get();
        $this->assertCount(2, $ready);

        foreach ($ready as $user) {
            $this->assertStringEndsWith('@lais.invalid', $user->email);
            $this->assertNotNull($user->email_verified_at);
            $this->assertStringStartsWith('warung-bu-rina-', $user->business->slug);
            $this->assertTrue($user->business->transactions()->where('type', '!=', TransactionType::SaldoAwal)->exists());
            $this->assertSame(5, $user->business->items()->whereNotNull('photo_path')->count());
        }

        $this->assertNotSame($ready[0]->business->slug, $ready[1]->business->slug);
    }

    public function test_each_visitor_is_signed_in_to_an_account_of_their_own(): void
    {
        $this->artisan('lais:demo');

        $this->post(route('demo.start'))->assertRedirect(route('dashboard'));
        $first = Auth::user();
        $this->assertTrue($first->is_demo);
        $this->assertEqualsWithDelta(now()->addHours(DemoAccounts::HOURS)->timestamp, $first->demo_until->timestamp, 5);
        $this->get(route('dashboard'))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user.is_demo', true));

        Auth::logout();
        $this->post(route('demo.start'));
        $second = Auth::user();
        $this->assertNotSame($first->id, $second->id);

        // The pool is empty now: the next visitor still gets one, made on the spot.
        Auth::logout();
        $this->post(route('demo.start'))->assertRedirect(route('dashboard'));
        $third = Auth::user();
        $this->assertNotContains($third->id, [$first->id, $second->id]);
        $this->assertTrue($third->business->transactions()->exists());
    }

    public function test_someone_signed_in_stays_who_they_are(): void
    {
        $owner = Business::factory()->create()->user;

        $this->actingAs($owner)->post(route('demo.start'))->assertRedirect(route('dashboard'));

        $this->assertSame($owner->id, Auth::id());
        $this->assertFalse(User::query()->where('is_demo', true)->exists());
    }

    public function test_accounts_that_ran_out_go_with_their_whole_business(): void
    {
        $owner = Business::factory()->create()->user;
        $demo = app(DemoAccounts::class);
        $claimed = $demo->claim();
        $business = $claimed->business;
        $photos = $business->items()->whereNotNull('photo_path')->pluck('photo_path')->all();

        // An order taken and finished: orders and transactions point at each other.
        $orders = app(Orders::class);
        $water = $business->items()->where('name', 'Air Mineral 600 ml')->sole();
        $order = $orders->place($business, ['customer_name' => 'Bu Sari', 'customer_phone' => '081299990000', 'fulfillment' => 'ambil', 'lines' => [['item_id' => $water->id, 'qty' => 1]]], TransactionSource::Toko);
        $orders->accept($order);
        $orders->complete($order->fresh());

        $this->travel(DemoAccounts::HOURS + 1)->hours();
        $this->artisan('lais:demo')->assertSuccessful();

        $this->assertNull($claimed->fresh());
        $this->assertNull($business->fresh());
        foreach (['transactions', 'orders', 'items', 'journal_entries', 'contacts', 'money_accounts', 'accounts'] as $table) {
            $this->assertSame(0, DB::table($table)->where('business_id', $business->id)->count(), $table);
        }
        Storage::disk(Media::diskName())->assertMissing($photos);

        // Accounts made on an earlier day are replaced, so their history ends today; real owners stay.
        $this->assertSame(2, User::query()->where('is_demo', true)->whereNull('demo_until')->where('created_at', '>=', today())->count());
        $this->assertNotNull($owner->fresh());
    }

    public function test_an_owner_with_books_can_delete_their_account(): void
    {
        $user = app(DemoAccounts::class)->prepare();
        $user->forceFill(['is_demo' => false, 'password' => 'rahasia-pemilik'])->save();
        $businessId = $user->business->id;

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'rahasia-pemilik'])
            ->assertRedirect('/');

        $this->assertNull($user->fresh());
        $this->assertSame(0, DB::table('transactions')->where('business_id', $businessId)->count());
    }
}
