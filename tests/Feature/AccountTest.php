<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\TransactionStatus;
use App\Models\Account;
use App\Models\Business;
use App\Models\JournalLine;
use App\Models\Transaction;
use App\Services\Reports;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();
        $this->actingAs($this->business->user);
    }

    public function test_an_owner_adds_income_and_expense_accounts_and_the_codes_follow_on(): void
    {
        $this->post(route('accounts.store'), ['name' => 'Beban Internet', 'type' => 'beban'])->assertRedirect();
        $this->post(route('accounts.store'), ['name' => 'Beban Parkir', 'type' => 'beban'])->assertRedirect();
        $this->post(route('accounts.store'), ['name' => 'Pendapatan Bunga', 'type' => 'pendapatan'])->assertRedirect();

        $this->assertSame(['4201', '6501', '6502'], Account::query()->where('business_id', $this->business->id)->orderBy('code')->pluck('code')->all());
        $this->assertSame('credit', Account::query()->where('code', '4201')->sole()->normal_balance);
    }

    public function test_only_income_and_expenses_can_be_added_and_names_stay_unique(): void
    {
        $this->post(route('accounts.store'), ['name' => 'Utang Bank', 'type' => 'kewajiban'])->assertSessionHasErrors('type');
        $this->post(route('accounts.store'), ['name' => 'Kas Tunai', 'type' => 'beban'])->assertSessionHasErrors('name');
        $this->post(route('accounts.store'), ['name' => 'Beban Internet', 'type' => 'beban']);
        $this->post(route('accounts.store'), ['name' => 'Beban Internet', 'type' => 'beban'])->assertSessionHasErrors('name');
    }

    public function test_the_fixed_chart_is_locked_and_another_business_cannot_touch_an_account(): void
    {
        $system = Account::query()->where('code', Account::CASH)->sole();
        $this->put(route('accounts.update', $system), ['name' => 'Laci'])->assertForbidden();
        $this->patch(route('accounts.archive', $system))->assertForbidden();
        $this->assertSame('Kas Tunai', $system->refresh()->name);

        $theirs = $this->own(Business::factory()->create(), 'Beban Mereka');
        $this->put(route('accounts.update', $theirs), ['name' => 'Punya saya'])->assertNotFound();
        $this->patch(route('accounts.archive', $theirs))->assertNotFound();
        $this->get(route('reports.ledger', $theirs->code))->assertNotFound();
    }

    public function test_the_type_of_an_account_cannot_change(): void
    {
        $account = $this->own($this->business, 'Beban Internet');

        $this->put(route('accounts.update', $account), ['name' => 'Beban Wifi', 'type' => 'pendapatan'])->assertSessionHasErrors('type');
        $this->put(route('accounts.update', $account), ['name' => 'Beban Wifi'])->assertSessionHasNoErrors();
        $this->assertSame('Beban Wifi', $account->refresh()->name);
    }

    public function test_an_expense_on_an_own_account_reaches_the_profit_and_every_report(): void
    {
        $account = $this->own($this->business, 'Beban Internet');
        $this->opening(500000);

        $this->post(route('transactions.store'), ['type' => 'biaya', 'kind' => 'lain', 'account_id' => $account->id, 'method' => 'tunai', 'amount' => 30000])->assertSessionHasNoErrors();

        $reports = app(Reports::class);
        $pnl = $reports->profitAndLoss($this->business, now()->startOfMonth());
        $this->assertSame([0, 30000, -30000], [$pnl['expenses'], $pnl['otherExpenses'], $pnl['netProfit']]);
        $this->assertSame('Beban Internet', Transaction::query()->where('type', 'biaya')->sole()->description);
        $this->assertSame([['code' => '6501', 'name' => 'Beban Internet', 'type' => $account->type, 'amount' => 30000]], $reports->ownAccounts($this->business, now()->startOfMonth()));
        $this->assertTrue($reports->balanceSheet($this->business, now())['balanced']);
        $this->assertSame(0, $reports->cashReconciliation($this->business, now()->startOfMonth())['difference']);
    }

    public function test_other_income_is_recorded_on_its_account_and_can_be_cancelled(): void
    {
        $income = $this->own($this->business, 'Pendapatan Hibah', 'pendapatan');

        $this->post(route('transactions.store'), ['type' => 'pemasukan_lain', 'account_id' => $income->id, 'method' => 'tunai', 'amount' => 100000])->assertSessionHasNoErrors();

        $reports = app(Reports::class);
        $pnl = $reports->profitAndLoss($this->business, now()->startOfMonth());
        $this->assertSame([0, 100000, 100000], [$pnl['sales'], $pnl['otherIncome'], $pnl['netProfit']]);
        $this->assertTrue($reports->balanceSheet($this->business, now())['balanced']);
        $this->assertSame(0, $reports->cashReconciliation($this->business, now()->startOfMonth())['difference']);

        $transaction = Transaction::query()->where('type', 'pemasukan_lain')->sole();
        app(TransactionService::class)->cancel($transaction);

        $this->assertSame(TransactionStatus::Void, $transaction->refresh()->status);
        $this->assertSame(0, $reports->profitAndLoss($this->business, now()->startOfMonth())['netProfit']);
    }

    public function test_a_record_can_only_name_an_active_own_account_of_the_right_type(): void
    {
        $expense = $this->own($this->business, 'Beban Internet');
        $income = $this->own($this->business, 'Pendapatan Hibah', 'pendapatan');
        $theirs = $this->own(Business::factory()->create(), 'Beban Mereka');
        $system = Account::query()->where('code', Account::SALES)->sole();
        $sent = fn (int $id) => $this->post(route('transactions.store'), ['type' => 'pemasukan_lain', 'account_id' => $id, 'method' => 'tunai', 'amount' => 1000]);

        $sent($expense->id)->assertSessionHasErrors('account_id');
        $sent($system->id)->assertSessionHasErrors('account_id');
        $this->post(route('transactions.store'), ['type' => 'pemasukan_lain', 'method' => 'tunai', 'amount' => 1000])->assertSessionHasErrors('account_id');
        $this->post(route('transactions.store'), ['type' => 'biaya', 'kind' => 'lain', 'account_id' => $theirs->id, 'method' => 'tunai', 'amount' => 1000])->assertSessionHasErrors('account_id');
        $this->post(route('transactions.store'), ['type' => 'penjualan', 'account_id' => $expense->id, 'method' => 'tunai'])->assertSessionHasErrors('account_id');

        $this->patch(route('accounts.archive', $income));
        $sent($income->id)->assertSessionHasErrors('account_id');
        $this->patch(route('accounts.archive', $income));
        $sent($income->id)->assertSessionHasNoErrors();
    }

    public function test_the_same_code_in_two_businesses_never_mixes(): void
    {
        $mine = $this->own($this->business, 'Beban Internet');
        $theirs = $this->own(Business::factory()->create(), 'Beban Listrik Toko');
        $this->assertSame($mine->code, $theirs->code);

        $this->post(route('transactions.store'), ['type' => 'biaya', 'kind' => 'lain', 'account_id' => $mine->id, 'method' => 'tunai', 'amount' => 5000]);

        $this->assertSame(1, JournalLine::query()->where('account_id', $mine->id)->count());
        $this->assertSame(0, JournalLine::query()->where('account_id', $theirs->id)->count());
    }

    public function test_the_trial_balance_and_ledger_read_the_journal(): void
    {
        $account = $this->own($this->business, 'Beban Internet');
        $this->opening(500000);
        $this->post(route('transactions.store'), ['type' => 'biaya', 'kind' => 'lain', 'account_id' => $account->id, 'method' => 'tunai', 'amount' => 30000]);

        $this->get(route('reports.trial-balance'))->assertInertia(function (AssertableInertia $page) {
            $page->component('reports/trial-balance');
            $rows = collect($page->toArray()['props']['accounts']);

            $this->assertSame($rows->sum('debit'), $rows->sum('credit'));
            $this->assertSame(30000, $rows->firstWhere('code', '6501')['debit']);
            $this->assertSame(470000, $rows->firstWhere('code', Account::CASH)['debit']);
        });

        $this->get(route('reports.ledger', Account::CASH))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('reports/ledger')
            ->where('ledger.opening', 0)
            ->where('ledger.closing', 470000)
            ->has('ledger.lines', 2)
            ->where('ledger.lines.1.credit', 30000)
            ->where('ledger.lines.1.balance', 470000));

        $this->get(route('reports.chart'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('reports/chart')
            ->where('accounts', fn ($accounts) => collect($accounts)->firstWhere('code', '6501')['balance'] === 30000
                && collect($accounts)->firstWhere('code', Account::OWNER_CAPITAL)['balance'] === 500000));
    }

    private function own(Business $business, string $name, string $type = 'beban'): Account
    {
        $account = new Account([
            'code' => Account::nextCode($business, AccountType::from($type)),
            'name' => $name,
            'type' => $type,
            'normal_balance' => $type === 'beban' ? 'debit' : 'credit',
        ]);
        $account->business_id = $business->id;
        $account->save();

        return $account;
    }

    private function opening(int $amount): void
    {
        app(TransactionService::class)->record($this->business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => $amount]);
    }
}
