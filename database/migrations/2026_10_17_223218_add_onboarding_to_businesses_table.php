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
            // Picks the starter products the wizard offers (App\Enums\BusinessCategory).
            $table->string('category', 12)->nullable()->after('slug');
            // The day the books open: every saldo awal is dated then.
            $table->date('started_on')->nullable()->after('category');
            // What the wizard holds between steps, so it can be picked up later.
            $table->json('onboarding_draft')->nullable()->after('started_on');
            $table->timestamp('onboarded_at')->nullable()->after('onboarding_draft');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['category', 'started_on', 'onboarding_draft', 'onboarded_at']);
        });
    }
};
