<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kas & rekening: the business's own cash box and bank or e-wallet accounts,
 * as details of 1101 Kas Tunai and 1102 Kas Non-Tunai.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('money_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('name', 60);
            $table->timestamps();
            // Archived, never deleted: its history stays readable.
            $table->softDeletes();

            $table->unique(['business_id', 'name']);
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            // Set on every 1101/1102 line; App\Services\Ledger checks it.
            $table->foreignId('money_account_id')->nullable()->after('account_id')->constrained();
        });

        Schema::table('transactions', function (Blueprint $table) {
            // The rekening the money moved through; the source of a Pindah dana.
            $table->foreignId('money_account_id')->nullable()->after('method')->constrained();
            $table->foreignId('to_money_account_id')->nullable()->after('money_account_id')->constrained('money_accounts');
        });

        $this->backfill();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('to_money_account_id');
            $table->dropConstrainedForeignId('money_account_id');
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('money_account_id');
        });

        Schema::dropIfExists('money_accounts');
    }

    /**
     * Businesses that already exist get the two default rekening, and their
     * cash lines and transactions are pointed at them (1101 → Kas tunai,
     * 1102 → Rekening).
     */
    private function backfill(): void
    {
        $codes = DB::table('accounts')->whereIn('code', ['1101', '1102'])->pluck('id', 'code');

        foreach (DB::table('businesses')->pluck('id') as $businessId) {
            $now = now();
            $cash = DB::table('money_accounts')->insertGetId(['business_id' => $businessId, 'kind' => 'tunai', 'name' => 'Kas tunai', 'created_at' => $now, 'updated_at' => $now]);
            $bank = DB::table('money_accounts')->insertGetId(['business_id' => $businessId, 'kind' => 'non_tunai', 'name' => 'Rekening', 'created_at' => $now, 'updated_at' => $now]);
            $entries = DB::table('journal_entries')->where('business_id', $businessId)->select('id');

            DB::table('journal_lines')->whereIn('journal_entry_id', $entries)->where('account_id', $codes['1101'])->update(['money_account_id' => $cash]);
            DB::table('journal_lines')->whereIn('journal_entry_id', $entries)->where('account_id', $codes['1102'])->update(['money_account_id' => $bank]);
            DB::table('transactions')->where('business_id', $businessId)->where('method', 'tunai')->update(['money_account_id' => $cash]);
            DB::table('transactions')->where('business_id', $businessId)->where('method', 'non_tunai')->update(['money_account_id' => $bank]);
        }
    }
};
