<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fixed chart of accounts (PRD §4.4). The owner never sees these.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private const ACCOUNTS = [
        ['1101', 'Kas Tunai', 'aset', 'debit'],
        ['1102', 'Kas Non-Tunai', 'aset', 'debit'],
        ['1201', 'Piutang Usaha', 'aset', 'debit'],
        ['1301', 'Persediaan', 'aset', 'debit'],
        ['1401', 'Peralatan', 'aset', 'debit'],
        ['2101', 'Utang Usaha', 'kewajiban', 'credit'],
        ['2201', 'Uang Muka Pesanan', 'kewajiban', 'credit'],
        ['3101', 'Modal Pemilik', 'ekuitas', 'credit'],
        ['3201', 'Prive', 'ekuitas', 'debit'],
        ['4101', 'Penjualan', 'pendapatan', 'credit'],
        ['5101', 'HPP', 'beban', 'debit'],
        ['6101', 'Beban Operasional', 'beban', 'debit'],
        ['6201', 'Selisih Persediaan', 'beban', 'debit'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 4)->unique();
            $table->string('name');
            $table->string('type', 12);
            $table->string('normal_balance', 6);
        });

        DB::table('accounts')->insert(array_map(
            fn (array $a) => ['code' => $a[0], 'name' => $a[1], 'type' => $a[2], 'normal_balance' => $a[3]],
            self::ACCOUNTS,
        ));

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->timestamp('occurred_at');
            $table->string('description');
            $table->nullableMorphs('source');
            $table->timestamps();

            $table->index(['business_id', 'occurred_at']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->unsignedBigInteger('debit')->default(0);
            $table->unsignedBigInteger('credit')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};
