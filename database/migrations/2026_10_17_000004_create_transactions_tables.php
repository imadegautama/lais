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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->string('name', 120);
            $table->string('phone', 20)->nullable();
            $table->timestamps();

            // Lets the form create a contact inline without making duplicates.
            $table->unique(['business_id', 'type', 'name']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('type', 15);
            $table->string('kind', 20)->nullable();
            $table->string('method', 10);
            $table->timestamp('occurred_at');
            $table->unsignedBigInteger('total');
            // The part of a credit sale paid on the spot.
            $table->unsignedBigInteger('paid_amount')->default(0);
            // Signed money into (+) or out of (−) the business till, taken from the journal's cash lines.
            $table->bigInteger('cash_flow');
            $table->string('description');
            $table->string('note', 200)->nullable();
            $table->foreignId('contact_id')->nullable()->constrained();
            $table->string('source', 5)->default('web');
            $table->string('status', 6)->default('posted');
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 200)->nullable();
            $table->timestamps();

            $table->index(['business_id', 'occurred_at']);
        });

        Schema::create('transaction_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->decimal('qty', 12, 3);
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('subtotal');
            // Sales only: rupiah cost of this line, equal to its share of the HPP journal line.
            $table->bigInteger('hpp')->nullable();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('stock_after', 12, 3)->nullable()->after('qty');
            $table->foreignId('transaction_id')->nullable()->after('journal_entry_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaction_id');
            $table->dropColumn('stock_after');
        });
        Schema::dropIfExists('transaction_lines');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('contacts');
    }
};
