<?php

namespace Tests\Feature;

use App\Enums\BusinessCategory;
use App\Models\Account;
use App\Models\Business;
use App\Models\Item;
use App\Models\JournalLine;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger;
use App\Services\TransactionService;
use App\Support\StarterCatalog;
use Database\Seeders\DemoBusinessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_wizard_opens_the_books_with_a_balanced_first_balance_sheet(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($business->user);

        $this->post(route('onboarding.store'), $this->wizard($business))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', ['tur' => 1]))
            ->assertInertiaFlash('toast.message', 'Pembukuan sudah dibuka.');

        $business->refresh();
        $ledger = app(Ledger::class);
        $balance = fn (string $code) => $ledger->balance($business, $code);
        $this->assertNotNull($business->onboarded_at);
        $this->assertNull($business->onboarding_draft);
        $this->assertSame(['Warung Ani', BusinessCategory::Kuliner, today()->toDateString()], [$business->name, $business->category, $business->started_on->toDateString()]);
        $this->assertSame(['Kas tunai', 'BCA', 'GoPay'], $business->moneyAccounts()->orderBy('id')->pluck('name')->all());
        $this->assertSame(2_600_000, $balance('1101') + $balance('1102'));

        // The kuliner starter catalog, recipes included, with its opening stock dated on the start day.
        $this->assertSame(12, $business->items()->count());
        $this->assertSame(5, Item::query()->where('name', 'Nasi Goreng')->sole()->recipeLines()->count());
        $persediaan = (int) $business->items()->sum('stock_value');
        $this->assertSame($persediaan, $balance('1301'));

        // Modal awal = aset awal − utang awal, so the first balance sheet balances.
        $this->assertSame(2_600_000 + 1_500_000 + $persediaan + 50_000 - 300_000, $balance('3101'));
        $this->assertSame(
            $balance('1101') + $balance('1102') + $balance('1201') + $balance('1301') + $balance('1401'),
            $balance('2101') + $balance('3101'),
        );
        $this->assertSame((int) JournalLine::query()->sum('debit'), (int) JournalLine::query()->sum('credit'));
        $this->assertSame([today()->startOfDay()->toDateTimeString()], Transaction::query()->pluck('occurred_at')->map->toDateTimeString()->unique()->values()->all());
        $this->assertSame(['saldo_awal'], Transaction::query()->distinct()->pluck('type')->map->value->all());
    }

    public function test_the_akun_step_adds_the_owners_own_accounts_and_refuses_names_already_taken(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($business->user);
        $refused = fn (array $accounts) => $this->post(route('onboarding.store'), ['custom_accounts' => $accounts] + $this->wizard($business));

        // A fixed account's name, the same name twice, and a type that is not income or expense.
        $refused([['type' => 'beban', 'name' => 'Beban Sewa']])->assertSessionHasErrors('custom_accounts.0.name');
        $refused([['type' => 'beban', 'name' => 'Beban Kemasan'], ['type' => 'beban', 'name' => 'Beban Kemasan']])->assertSessionHasErrors('custom_accounts.1.name');
        $refused([['type' => 'aset', 'name' => 'Laci kasir']])->assertSessionHasErrors('custom_accounts.0.type');
        $this->assertNull($business->refresh()->onboarded_at);

        $refused([['type' => 'beban', 'name' => 'Beban Kemasan'], ['type' => 'pendapatan', 'name' => 'Pendapatan Jasa Antar'], ['type' => 'beban', 'name' => 'Beban Internet & pulsa']])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['4201' => 'Pendapatan Jasa Antar', '6501' => 'Beban Kemasan', '6502' => 'Beban Internet & pulsa'],
            Account::query()->where('accounts.business_id', $business->id)->orderBy('accounts.code')->pluck('accounts.name', 'accounts.code')->all(),
        );
        $this->assertNotNull($business->refresh()->onboarded_at);
    }

    public function test_the_wizard_shows_the_fixed_chart_for_the_akun_step(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($business->user);

        $this->get(route('onboarding.show'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('chart.0.code', '1101')
            ->where('chart', fn ($chart) => collect($chart)->pluck('code')->contains('6104')));
    }

    public function test_a_draft_is_saved_after_each_step_checking_only_that_step(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($business->user);

        $this->patch(route('onboarding.update'), ['step' => 'usaha', 'name' => '', 'draft' => ['name' => '']])->assertSessionHasErrors(['name' => 'Tulis nama usahanya.']);
        // Later steps are not checked yet; the form is kept as typed, quantities as text.
        $this->patch(route('onboarding.update'), [
            'step' => 'usaha', 'name' => 'Warung Ani', 'started_on' => today()->toDateString(), 'products' => [['name' => '']],
            'draft' => ['step' => 'usaha', 'name' => 'Warung Ani', 'products' => [['name' => '', 'opening_stock' => '2,5', 'example' => true]], 'stray' => 'dibuang'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['step', 'name', 'products'], array_keys($business->refresh()->onboarding_draft));

        $this->get(route('onboarding.show'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('onboarding/index')
            ->where('draft.step', 'usaha')
            ->where('draft.name', 'Warung Ani')
            ->where('draft.products.0.opening_stock', '2,5')
            ->where('latestStart', today()->toDateString())
            ->has('templates.kuliner', 12)
            ->has('templates.lainnya', 0)
            ->has('accounts', 2));
        $this->assertNull($business->refresh()->onboarded_at);
    }

    public function test_the_books_cannot_open_after_the_first_record_or_in_the_future(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($business->user);
        app(TransactionService::class)->record($business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 1000, 'occurred_at' => '2026-10-10 09:00']);

        $this->patch(route('onboarding.update'), ['step' => 'usaha', 'name' => 'Warung Ani', 'started_on' => '2026-10-11', 'draft' => []])
            ->assertSessionHasErrors(['started_on' => 'Paling lambat 10 Oktober 2026: saldo awal harus ada sebelum catatan pertama, dan tidak boleh di masa depan.']);
        $this->patch(route('onboarding.update'), ['step' => 'usaha', 'name' => 'Warung Ani', 'started_on' => '2026-10-10', 'draft' => ['name' => 'Warung Ani']])->assertSessionHasNoErrors();
    }

    public function test_a_recipe_naming_an_ingredient_that_is_not_there_is_reported_on_its_row(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($business->user);
        $wizard = ['products' => [['name' => 'Es Jeruk', 'kind' => 'komposisi', 'unit' => 'gelas', 'price' => 6000, 'recipe' => [['name' => 'Jeruk peras', 'qty' => 2]]]]] + $this->wizard($business);

        $this->post(route('onboarding.store'), $wizard)->assertSessionHasErrors(['products.0.recipe' => 'Bahan Jeruk peras untuk Es Jeruk tidak ada di daftar.']);
        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_the_reminder_shows_until_the_books_are_opened(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($business->user);

        $this->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page->where('needsOnboarding', true));
        $this->get(route('money-accounts.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('needsOnboarding', true));

        $business->update(['onboarded_at' => now()]);

        $this->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page->where('needsOnboarding', false));
        $this->get(route('onboarding.show'))->assertRedirect(route('dashboard'));
    }

    public function test_the_demo_seeder_opens_the_books_the_way_the_wizard_does(): void
    {
        $user = User::factory()->create(['email' => 'demo@lais.test']);

        $this->seed(DemoBusinessSeeder::class);

        $business = $user->business;
        $balances = app(Ledger::class)->moneyBalances($business);
        $this->assertNotNull($business->onboarded_at);
        $this->assertSame(count(StarterCatalog::for(BusinessCategory::Kuliner)), $business->items()->count());
        $this->assertSame([1_500_000, 6_000_000, 0], $business->moneyAccounts()->orderBy('id')->get()->map(fn ($account) => $balances[$account->id] ?? 0)->all());
    }

    /**
     * A full wizard: three rekening (one new), equipment, the kuliner starter
     * products, one debt and one receivable.
     *
     * @return array<string, mixed>
     */
    private function wizard(Business $business): array
    {
        [$cashBox, $rekening] = $business->moneyAccounts()->orderBy('id')->get()->all();

        return [
            'name' => 'Warung Ani',
            'category' => 'kuliner',
            'started_on' => today()->toDateString(),
            'accounts' => [
                ['id' => $cashBox->id, 'name' => 'Kas tunai', 'balance' => 500_000],
                ['id' => $rekening->id, 'name' => 'BCA', 'balance' => 2_000_000],
                ['id' => null, 'name' => 'GoPay', 'balance' => 100_000],
            ],
            'equipment' => [['name' => 'Kompor dua tungku', 'amount' => 1_500_000]],
            'products' => array_map(fn (array $row) => ['kind' => $row['kind']->value] + $row, StarterCatalog::for(BusinessCategory::Kuliner)),
            'debts' => [['name' => 'Toko Sumber', 'amount' => 300_000]],
            'receivables' => [['name' => 'Bu Sari', 'amount' => 50_000]],
        ];
    }
}
