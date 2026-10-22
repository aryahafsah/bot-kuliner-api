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
        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('telegram_update_id')->unique();
            $table->bigInteger('chat_id');
            $table->string('message_type');
            $table->json('message_metadata')->nullable();
            $table->timestamps();
        });
        Schema::table('telegram_messages', function (Blueprint $table) {
            $table->index('chat_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
    }
};
