<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Peralatan are depreciated from now on (SAK EMKM par. 11.8). What was
     * already bought gets a row with no useful life: nothing is posted until
     * the owner sets it, so no existing book changes on its own.
     */
    public function up(): void
    {
        DB::table('accounts')->insert([
            ['code' => '1402', 'name' => 'Akumulasi Penyusutan', 'type' => 'aset', 'normal_balance' => 'credit'],
            ['code' => '6301', 'name' => 'Beban Penyusutan', 'type' => 'beban', 'normal_balance' => 'debit'],
        ]);

        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->unique()->constrained();
            $table->string('name');
            $table->unsignedBigInteger('cost');
            $table->date('acquired_on');
            $table->unsignedSmallInteger('useful_life_months')->nullable();
            // The last month-end already posted.
            $table->date('depreciated_through')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        DB::table('transactions')
            ->whereIn('kind', ['peralatan', 'saldo_peralatan'])
            ->where('status', 'posted')
            ->orderBy('id')
            ->get()
            ->each(fn (object $transaction) => DB::table('fixed_assets')->insert([
                'business_id' => $transaction->business_id,
                'transaction_id' => $transaction->id,
                'name' => mb_strimwidth((string) ($transaction->note ?? 'Peralatan'), 0, 255),
                'cost' => $transaction->total,
                'acquired_on' => substr((string) $transaction->occurred_at, 0, 10),
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
        DB::table('accounts')->whereNull('business_id')->whereIn('code', ['1402', '6301'])->delete();
    }
};
