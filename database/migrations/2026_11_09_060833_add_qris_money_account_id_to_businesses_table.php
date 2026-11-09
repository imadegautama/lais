<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // Where QRIS money lands, so "jual … qris" needs no rekening named (bot, Tanya AI, order commands).
            $table->foreignId('qris_money_account_id')->nullable()->after('qris_path')->constrained('money_accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qris_money_account_id');
        });
    }
};
