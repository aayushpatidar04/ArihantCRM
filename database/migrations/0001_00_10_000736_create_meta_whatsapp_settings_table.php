<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meta_whatsapp_settings', function (Blueprint $table) {
            $table->id();

            // Internal name
            $table->string('name');

            // Meta Developer App
            $table->string('app_id')->unique();
            $table->text('app_secret');

            // Used for Meta webhook verification
            $table->text('verify_token');

            // Optional webhook configuration
            $table->string('webhook_url')->nullable();

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamp('last_webhook_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_whatsapp_settings');
    }
};