<?php

namespace Tests\Feature;

use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\Business;
use App\Models\MoneyAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MoneyAccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_business_starts_with_kas_tunai_and_one_rekening_shown_with_their_balances(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($business->user);
        $this->record($business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'tunai', 'amount' => 100_000]);
        $this->record($business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'non_tunai', 'amount' => 50_000]);

        $this->get(route('money-accounts.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('money-accounts/index')
            ->where('accounts', [
                ['id' => $business->cashBox()->id, 'kind' => 'tunai', 'name' => 'Kas tunai', 'balance' => 100_000],
                ['id' => $this->rekening($business)->id, 'kind' => 'non_tunai', 'name' => 'Rekening', 'balance' => 50_000],
            ]));
        // The defaults are the system's, not something the owner did.
        $this->assertSame(0, Activity::query()->where('log_name', 'rekening')->count());
    }

    public function test_adding_and_renaming_a_rekening_is_logged_and_names_stay_unique(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($business->user);

        $this->post(route('money-accounts.store'), ['name' => 'GoPay'])->assertSessionHasNoErrors();
        $gopay = $business->moneyAccounts()->where('name', 'GoPay')->sole();
        $this->put(route('money-accounts.update', $gopay), ['name' => 'GoPay Usaha'])->assertSessionHasNoErrors();
        $this->post(route('money-accounts.store'), ['name' => 'Kas tunai'])->assertSessionHasErrors(['name' => 'Nama ini sudah dipakai rekening lain.']);

        $this->assertSame('non_tunai', $gopay->refresh()->kind->value);
        $entries = Activity::query()->forSubject($gopay)->oldest('id')->get();
        $this->assertSame(['Menambah rekening GoPay', 'Mengubah rekening GoPay Usaha'], $entries->pluck('description')->all());
        $this->assertSame(
            [['label' => 'Nama', 'old' => 'GoPay', 'new' => 'GoPay Usaha']],
            ActivityResource::make($entries[1]->load(['causer', 'subject']))->resolve()['changes'],
        );
    }

    public function test_archiving_waits_until_the_money_is_moved_and_keeps_kas_tunai_and_the_last_rekening(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($business->user);
        $gopay = MoneyAccount::factory()->for($business)->create(['name' => 'GoPay']);
        $this->record($business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'non_tunai', 'money_account_id' => $gopay->id, 'amount' => 10_000]);

        $this->delete(route('money-accounts.destroy', $business->cashBox()))->assertInertiaFlash('toast.message', 'Kas tunai tidak bisa diarsipkan.');
        $this->delete(route('money-accounts.destroy', $gopay))->assertInertiaFlash('toast.message', 'Saldo GoPay masih ada. Pindahkan dulu ke rekening lain lewat Pindah dana.');

        $this->record($business, ['type' => 'pindah_dana', 'money_account_id' => $gopay->id, 'to_money_account_id' => $business->cashBox()->id, 'amount' => 10_000]);
        $this->delete(route('money-accounts.destroy', $gopay))->assertInertiaFlash('toast.message', 'GoPay diarsipkan.');
        $this->delete(route('money-accounts.destroy', $this->rekening($business)))->assertInertiaFlash('toast.message', 'Sisakan minimal satu rekening non-tunai. Ganti saja namanya.');

        $this->assertSoftDeleted($gopay);
        $this->assertSame('Mengarsipkan rekening GoPay', Activity::query()->forSubject($gopay)->where('event', 'deleted')->sole()->description);
        $this->get(route('money-accounts.index'))->assertInertia(fn (AssertableInertia $page) => $page->has('accounts', 2));
    }

    public function test_a_record_on_an_archived_rekening_cannot_be_cancelled(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($business->user);
        $gopay = MoneyAccount::factory()->for($business)->create(['name' => 'GoPay']);
        $deposit = $this->record($business, ['type' => 'modal', 'kind' => 'setor', 'method' => 'non_tunai', 'money_account_id' => $gopay->id, 'amount' => 10_000]);
        $this->record($business, ['type' => 'pindah_dana', 'money_account_id' => $gopay->id, 'to_money_account_id' => $business->cashBox()->id, 'amount' => 10_000]);
        $gopay->delete();

        $this->post(route('transactions.cancel', $deposit))
            ->assertSessionHasErrors(['transaction' => 'Rekening GoPay sudah diarsipkan, jadi uangnya tidak bisa dikembalikan.']);
        $this->get(route('transactions.edit', $deposit))
            ->assertInertiaFlash('toast.message', 'GoPay sudah diarsipkan, jadi catatan ini tidak bisa diubah.');
    }

    public function test_another_businesses_rekening_is_not_found(): void
    {
        $foreign = Business::factory()->create()->cashBox();
        $this->actingAs(User::factory()->create());

        $this->put(route('money-accounts.update', $foreign), ['name' => 'Milik saya'])->assertNotFound();
        $this->delete(route('money-accounts.destroy', $foreign))->assertNotFound();
        $this->assertSame('Kas tunai', $foreign->refresh()->name);
    }

    private function rekening(Business $business): MoneyAccount
    {
        return $business->moneyAccounts()->where('name', 'Rekening')->sole();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function record(Business $business, array $data): Transaction
    {
        return app(TransactionService::class)->record($business, $data);
    }
}
