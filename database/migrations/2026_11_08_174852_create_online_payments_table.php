<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bayar online through DOKU Checkout: one row per payment page, and the
     * owner's switch to offer it in the shop.
     */
    public function up(): void
    {
        Schema::create('online_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // DOKU's order.invoice_number: letters and digits only, at most 30 characters.
            $table->string('invoice_number', 30)->unique();
            $table->unsignedBigInteger('amount');
            $table->string('url', 500);
            $table->string('status', 20);
            $table->string('channel', 60)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::table('businesses', fn (Blueprint $table) => $table->boolean('online_payment')->default(false));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', fn (Blueprint $table) => $table->dropColumn('online_payment'));
        Schema::dropIfExists('online_payments');
    }
};
