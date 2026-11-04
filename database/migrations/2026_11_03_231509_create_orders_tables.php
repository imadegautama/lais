<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pesanan (PRD M4): orders from the shop page or entered by the owner.
     * Money never sits on the order: payments and the sale are transactions
     * that point back to it.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained();
            $table->string('code', 10);
            // The customer's link to the nota; random, never the id (PRD US-08).
            $table->string('token', 32)->unique();
            $table->string('status', 10);
            $table->string('source', 5);
            $table->string('customer_name', 120);
            $table->string('customer_phone', 20);
            $table->string('fulfillment', 5);
            $table->string('address', 300)->nullable();
            $table->timestamp('wanted_at')->nullable();
            $table->string('note', 300)->nullable();
            $table->unsignedBigInteger('total');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('reject_reason', 200)->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'code']);
            $table->index(['business_id', 'status']);
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            // The name, unit and price when ordered, so a later catalog change leaves the nota alone.
            $table->string('name', 120);
            $table->string('unit', 20);
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('subtotal');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('contact_id')->constrained();
        });

        // The sale an order became, once it is done.
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('transaction_id')->nullable()->after('contact_id')->constrained();
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('accepting_orders')->default(true);
            $table->string('whatsapp', 20)->nullable();
            $table->string('qris_path')->nullable();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->string('photo_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('items', fn (Blueprint $table) => $table->dropColumn('photo_path'));
        Schema::table('businesses', fn (Blueprint $table) => $table->dropColumn(['accepting_orders', 'whatsapp', 'qris_path']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('transaction_id'));
        Schema::table('transactions', fn (Blueprint $table) => $table->dropConstrainedForeignId('order_id'));
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
    }
};
