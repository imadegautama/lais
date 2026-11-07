<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The WhatsApp channel (PRD US-06, US-09, M8): the owner's linked number,
     * the summary switch, and every message in or out.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 62… form; set only after the owner sent their link code to the bot.
            $table->string('whatsapp', 20)->nullable()->unique()->after('email');
            // WhatsApp's linked id (…@lid), which some senders arrive as instead of a number.
            $table->string('whatsapp_lid', 60)->nullable()->unique()->after('whatsapp');
            $table->timestamp('whatsapp_linked_at')->nullable()->after('whatsapp_lid');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('daily_summary')->default(true);
        });

        Schema::create('wa_messages', function (Blueprint $table) {
            $table->id();
            // GOWA's message id; unique so a webhook delivered twice is handled once.
            $table->string('message_id', 100)->nullable()->unique();
            $table->string('direction', 3);
            $table->string('phone', 60);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_messages');
        Schema::table('businesses', fn (Blueprint $table) => $table->dropColumn('daily_summary'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['whatsapp', 'whatsapp_lid', 'whatsapp_linked_at']));
    }
};
