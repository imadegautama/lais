<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\Services\Reports;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoBusinessSeeder;
use Database\Seeders\DemoHistorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoHistorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_history_leaves_books_a_judge_can_trust(): void
    {
        $business = $this->openedShop();

        $this->history();

        $this->assertGreaterThan(100, $business->transactions()->count());
        $this->assertTrue(app(Reports::class)->balanceSheet($business, CarbonImmutable::today())['balanced']);
        $this->assertSame(0, $business->items()->where('items.stock', '<', 0)->count());
    }

    public function test_the_history_refuses_books_that_already_have_records(): void
    {
        $business = $this->openedShop();
        $water = $business->items()->where('items.name', 'Air Mineral 600 ml')->firstOrFail();
        app(TransactionService::class)->record($business, ['type' => 'penjualan', 'method' => 'tunai', 'lines' => [['item_id' => $water->id, 'qty' => 1, 'price' => 4_000]]]);
        $before = $business->transactions()->count();

        $this->history();

        $this->assertSame($before, $business->transactions()->count());
    }

    public function test_running_the_history_twice_adds_nothing(): void
    {
        $business = $this->openedShop();
        $this->history();
        $once = $business->transactions()->count();

        $this->history();

        $this->assertSame($once, $business->transactions()->count());
    }

    private function openedShop(): Business
    {
        User::factory()->create(['email' => 'demo@lais.test']);
        $this->seed(DemoBusinessSeeder::class);

        return Business::query()->where('businesses.slug', 'warung-bu-rina')->firstOrFail();
    }

    /** Twelve days are enough here; the real run writes ninety. */
    private function history(): void
    {
        $seeder = app(DemoHistorySeeder::class);
        $seeder->days = 12;
        $seeder->run();
    }
}
