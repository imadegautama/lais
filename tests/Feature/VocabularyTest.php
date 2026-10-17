<?php

namespace Tests\Feature;

use App\Console\Commands\GenerateEnumTypes;
use App\Enums\ContactType;
use App\Enums\PaymentMethod;
use App\Enums\Period;
use App\Enums\TransactionKind;
use App\Enums\TransactionType;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VocabularyTest extends TestCase
{
    public function test_the_typescript_vocabulary_matches_the_enums(): void
    {
        $this->assertSame(
            GenerateEnumTypes::render(),
            file_get_contents(resource_path(GenerateEnumTypes::PATH)),
            'resources/js/types/enums.ts is out of date. Run php artisan lais:enums.',
        );
    }

    public function test_every_kind_belongs_to_exactly_one_type_and_sales_have_none(): void
    {
        $listed = array_merge(...array_map(fn (TransactionType $type) => $type->kinds(), TransactionType::cases()));

        $this->assertSame([], TransactionType::Penjualan->kinds());
        $this->assertEqualsCanonicalizing(TransactionKind::cases(), $listed);
        $this->assertCount(count(TransactionKind::cases()), $listed);
    }

    public function test_personal_money_is_only_for_purchases_and_expenses(): void
    {
        $allowing = array_filter(TransactionType::cases(), fn (TransactionType $type) => in_array(PaymentMethod::Pribadi, $type->methods(), true));

        $this->assertSame([TransactionType::Pembelian, TransactionType::Biaya], array_values($allowing));
    }

    public function test_customers_are_on_the_other_side_of_sales_and_their_payments(): void
    {
        $this->assertSame(ContactType::Pelanggan, ContactType::for(TransactionType::Penjualan, null));
        $this->assertSame(ContactType::Pelanggan, ContactType::for(TransactionType::UtangPiutang, TransactionKind::TerimaPiutang));
        $this->assertSame(ContactType::Supplier, ContactType::for(TransactionType::UtangPiutang, TransactionKind::BayarUtang));
        $this->assertSame(ContactType::Supplier, ContactType::for(TransactionType::Biaya, TransactionKind::Sewa));
    }

    public function test_periods_start_in_wib_and_weeks_start_on_monday(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 09:30', 'Asia/Jakarta'));

        $this->assertSame('2026-10-15 00:00', Period::HariIni->start()->format('Y-m-d H:i'));
        $this->assertSame('2026-10-12 00:00', Period::MingguIni->start()->format('Y-m-d H:i'));
        $this->assertSame('2026-10-01 00:00', Period::BulanIni->start()->format('Y-m-d H:i'));
    }
}
