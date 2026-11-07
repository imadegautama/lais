<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The fixed chart stays as it is (business_id null); a business can add
     * its own income and expense accounts, and an expense or other income can
     * name one. Existing rows are untouched.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->timestamp('archived_at')->nullable()->after('normal_balance');
            $table->dropUnique(['code']);
            $table->unique(['business_id', 'code']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('kind')->constrained();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'code']);
            $table->unique('code');
            $table->dropConstrainedForeignId('business_id');
            $table->dropColumn('archived_at');
        });
    }
};
