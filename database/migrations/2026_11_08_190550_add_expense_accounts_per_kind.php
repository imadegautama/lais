<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One expense account per kind of biaya, so the chart (COA) shows Beban
     * Sewa, Beban Listrik & Air and so on instead of one Beban Operasional.
     * 6101 stays for Lain-lain and keeps every biaya recorded before this,
     * which the reports still read per kind (Reports::expensesByKind).
     * Names are "Beban " + TransactionKind::label(), as the biaya form shows them.
     */
    public function up(): void
    {
        DB::table('accounts')->insert([
            ['code' => '6102', 'name' => 'Beban Listrik & air', 'type' => 'beban', 'normal_balance' => 'debit'],
            ['code' => '6103', 'name' => 'Beban Gas & BBM', 'type' => 'beban', 'normal_balance' => 'debit'],
            ['code' => '6104', 'name' => 'Beban Sewa', 'type' => 'beban', 'normal_balance' => 'debit'],
            ['code' => '6105', 'name' => 'Beban Upah karyawan', 'type' => 'beban', 'normal_balance' => 'debit'],
            ['code' => '6106', 'name' => 'Beban Transport & ongkir', 'type' => 'beban', 'normal_balance' => 'debit'],
            ['code' => '6107', 'name' => 'Beban Pemasaran', 'type' => 'beban', 'normal_balance' => 'debit'],
        ]);
    }

    public function down(): void
    {
        DB::table('accounts')->whereNull('business_id')->whereIn('code', ['6102', '6103', '6104', '6105', '6106', '6107'])->delete();
    }
};
