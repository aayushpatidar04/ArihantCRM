<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_numbers', function (Blueprint $table) {
            $table->id();

            // Meta / WhatsApp Business data
            $table->string('phone_number_id')->unique();
            $table->string('waba_id')->nullable();
            $table->string('business_account_id')->nullable();

            // Actual WhatsApp number
            $table->string('phone_number', 30)->unique();
            $table->string('display_phone_number', 30)->nullable();
            $table->string('verified_name')->nullable();

            // Official WhatsApp Cloud API access token
            $table->text('access_token');

            $table->boolean('is_active')->default(true);

            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_webhook_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('waba_id');
            $table->index('business_account_id');
            $table->index('meta_whatsapp_setting_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_numbers');
    }
};