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
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            // produk: sold with its own stock; bahan: stocked, not sold; komposisi: sold, made from a recipe.
            $table->string('kind', 12);
            $table->string('name', 120);
            $table->json('aliases')->nullable();
            $table->string('unit', 20);
            $table->unsignedBigInteger('price')->nullable();
            $table->decimal('stock', 12, 3)->default(0);
            // Rupiah value of the stock on hand; the average cost is stock_value / stock.
            $table->bigInteger('stock_value')->default(0);
            $table->decimal('min_stock', 12, 3)->nullable();
            $table->boolean('show_in_store')->default(true);
            $table->boolean('available')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'kind']);
        });

        Schema::create('recipe_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('items')->restrictOnDelete();
            $table->decimal('qty', 12, 3);
            $table->timestamps();

            $table->unique(['product_id', 'material_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_lines');
        Schema::dropIfExists('items');
        Schema::dropIfExists('businesses');
    }
};
