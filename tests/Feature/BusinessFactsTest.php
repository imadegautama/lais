<?php

namespace Tests\Feature;

use App\Enums\ContactType;
use App\Models\Business;
use App\Services\BusinessFacts;
use App\Services\Catalog;
use App\Services\Reports;
use App\Services\TransactionService;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessFactsTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-15 10:00:00'));
        $this->business = Business::factory()->create();
        app(Catalog::class)->createMany($this->business, [
            ['name' => 'Es Teh', 'kind' => 'produk', 'unit' => 'gelas', 'price' => 5000, 'opening_stock' => 100, 'opening_cost' => 2000],
        ]);
    }

    public function test_the_break_even_point_follows_the_formula_and_never_divides_by_nothing(): void
    {
        // 40% of each sale is left after goods and variable costs, so 400k of fixed costs needs 1 million.
        $this->assertSame(1000000, BusinessFacts::breakEven(500000, 250000, 50000, 400000));

        $this->assertNull(BusinessFacts::breakEven(0, 0, 0, 400000));
        $this->assertNull(BusinessFacts::breakEven(100000, 90000, 20000, 400000));
    }

    public function test_the_sheet_says_what_the_reports_say_and_capital_is_not_cash(): void
    {
        $service = app(TransactionService::class);
        $item = $this->business->items()->sole();
        $service->record($this->business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 500000, 'occurred_at' => '2026-10-02 09:00']);
        $service->record($this->business, ['type' => 'penjualan', 'method' => 'tunai', 'occurred_at' => '2026-10-10 09:00', 'lines' => [['item_id' => $item->id, 'qty' => 40, 'price' => 5000]]]);
        $service->record($this->business, ['type' => 'biaya', 'kind' => 'sewa', 'method' => 'tunai', 'amount' => 60000, 'occurred_at' => '2026-10-11 09:00']);
        $service->record($this->business, ['type' => 'biaya', 'kind' => 'pemasaran', 'method' => 'tunai', 'amount' => 20000, 'occurred_at' => '2026-10-12 09:00']);

        $sheet = app(BusinessFacts::class)->sheet($this->business);
        $reports = app(Reports::class);
        $position = $reports->balanceSheet($this->business, now());

        $this->assertStringContainsString('Modal pemilik (aset dikurangi kewajiban) '.Format::rupiah($position['equity']['total']), $sheet);
        $this->assertStringContainsString('Uang usaha (kas dan rekening) '.Format::rupiah($position['assets']['cash'] + $position['assets']['nonCash']), $sheet);
        $this->assertNotSame($position['equity']['total'], $position['assets']['cash'] + $position['assets']['nonCash']);
        $this->assertStringContainsString('Es Teh: 60 gelas', $sheet);
        $this->assertStringContainsString('paling untung Es Teh', $sheet);

        // October: sales 200k, goods 80k, variable 20k, fixed 60k. Margin 50% of sales, so break-even 120k.
        $this->assertStringContainsString('Biaya tetap Rp60.000 sebulan, biaya tidak tetap Rp20.000', $sheet);
        $this->assertStringContainsString('BEP penjualan Rp120.000 sebulan', $sheet);
        $this->assertStringContainsString('di atas BEP sebesar Rp80.000', $sheet);
        $this->assertStringContainsString('Es Teh: 20 gelas sebulan', $sheet);
    }

    public function test_without_sales_the_sheet_says_the_break_even_point_cannot_be_worked_out(): void
    {
        $this->assertStringContainsString('BEP belum bisa dihitung: bulan lalu belum ada penjualan.', app(BusinessFacts::class)->sheet($this->business));
    }

    public function test_the_sheet_holds_nothing_of_other_businesses_and_no_phone_numbers(): void
    {
        $other = Business::factory()->create();
        app(Catalog::class)->createMany($other, [['name' => 'Rahasia Tetangga', 'kind' => 'produk', 'unit' => 'pcs', 'price' => 1000, 'opening_stock' => 5, 'opening_cost' => 500]]);
        $contact = $this->business->contacts()->create(['type' => ContactType::Pelanggan, 'name' => 'Bu Sari', 'phone' => '081234567890']);
        app(TransactionService::class)->record($this->business, ['type' => 'penjualan', 'method' => 'kredit', 'contact_id' => $contact->id, 'occurred_at' => '2026-11-10 09:00', 'paid_amount' => 0, 'lines' => [['item_id' => $this->business->items()->sole()->id, 'qty' => 2, 'price' => 5000]]]);

        $sheet = app(BusinessFacts::class)->sheet($this->business);

        $this->assertStringContainsString('Bu Sari: Rp10.000', $sheet);
        $this->assertStringNotContainsString('Rahasia Tetangga', $sheet);
        $this->assertStringNotContainsString('081234567890', $sheet);
    }

    public function test_an_answer_may_only_quote_figures_from_the_sheet(): void
    {
        $sheet = 'Laba bersih Rp4.847.000, margin 72,7%, turun Rp100.000 (99%). Beras: 19,8 kg, 172 porsi.';

        foreach (['Laba Anda Rp4.847.000.', 'Marginnya 72,7%, sekitar 30 hari.', 'Turun 99% di tahun 2026.', 'Sisa 19,8 kg, atau 172 porsi.', 'Stoknya aman.'] as $answer) {
            $this->assertTrue(BusinessFacts::grounded($answer, $sheet), $answer);
        }

        foreach (['Laba Anda Rp4.900.000.', 'Kira-kira Rp4,8 jt.', 'Marginnya 75%.', 'Sekitar 180 porsi.', 'Sisa 20,5 kg.'] as $answer) {
            $this->assertFalse(BusinessFacts::grounded($answer, $sheet), $answer);
        }
    }
}
