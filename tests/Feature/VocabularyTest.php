<?php

namespace Tests\Feature;

use App\Console\Commands\GenerateEnumTypes;
use App\Enums\ContactType;
use App\Enums\GuideTopic;
use App\Enums\PaymentMethod;
use App\Enums\Period;
use App\Enums\TransactionKind;
use App\Enums\TransactionType;
use App\Services\Ai\TransactionDraft;
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
        $chosen = array_values(array_filter(TransactionKind::cases(), fn (TransactionKind $kind) => $kind !== TransactionKind::UangMuka));

        $this->assertSame([], TransactionType::Penjualan->kinds());
        $this->assertEqualsCanonicalizing($chosen, $listed);
        $this->assertCount(count($chosen), $listed);
        // Uang muka belongs to Utang-piutang but is never chosen by hand: an order records it.
        $this->assertSame(TransactionType::UtangPiutang, TransactionKind::UangMuka->type());
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

    public function test_every_guide_topic_explains_itself_and_drafts_only_what_the_assistant_fills(): void
    {
        foreach (GuideTopic::cases() as $topic) {
            $this->assertNotSame('', $topic->explanation());
            $this->assertSame($topic === GuideTopic::BelumDidukung, $topic->needs() === []);

            $draft = $topic->draft();

            if ($draft !== null) {
                $this->assertContains(TransactionType::from($draft['jenis']), TransactionDraft::TYPES);
                $this->assertNotSame('', $topic->actionLabel());

                if (isset($draft['jenis_biaya'])) {
                    $this->assertSame($draft['jenis'], TransactionKind::from($draft['jenis_biaya'])->type()->value);
                }
            }
        }
    }

    public function test_periods_start_in_wib_and_weeks_start_on_monday(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 09:30', 'Asia/Jakarta'));

        $this->assertSame('2026-10-15 00:00', Period::HariIni->start()->format('Y-m-d H:i'));
        $this->assertSame('2026-10-12 00:00', Period::MingguIni->start()->format('Y-m-d H:i'));
        $this->assertSame('2026-10-01 00:00', Period::BulanIni->start()->format('Y-m-d H:i'));
    }
}
