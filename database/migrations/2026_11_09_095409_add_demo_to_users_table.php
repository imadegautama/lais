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
        // "Coba demo" (App\Services\DemoAccounts): a throwaway account with the Warung Bu Rina books.
        // Ready in the pool while demo_until is null; once claimed, it is removed after demo_until.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('tour_completed_at')->index();
            $table->timestamp('demo_until')->nullable()->after('is_demo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_demo']);
            $table->dropColumn(['is_demo', 'demo_until']);
        });
    }
};
