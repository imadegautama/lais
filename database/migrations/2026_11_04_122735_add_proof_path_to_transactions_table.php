<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bukti transaksi (PRD M2): one photo, on the private disk (App\Support\Proof).
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('proof_path')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', fn (Blueprint $table) => $table->dropColumn('proof_path'));
    }
};
