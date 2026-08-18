<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();

            /*
             * The WhatsApp number this template belongs to.
             *
             * Templates are number-specific in our application.
             */
            $table->foreignId('whatsapp_number_id')
                ->constrained('whatsapp_numbers')
                ->cascadeOnDelete();

            /*
             * Meta's template ID.
             */
            $table->string('template_id')->nullable();

            /*
             * Meta template identity.
             */
            $table->string('name');
            $table->string('language', 50);
            $table->string('category', 50)->nullable();
            $table->string('status', 50)->nullable();

            /*
             * Complete template structure received from Meta.
             *
             * This remains the Meta-approved structure.
             */
            $table->json('components')->nullable();

            /*
             * Our application's editable configuration.
             *
             * Example:
             *
             * {
             *     "header_media_url": "...",
             *     "variables": {
             *         "1": "customer.name",
             *         "2": "order.number"
             *     }
             * }
             */
            $table->json('local_config')->nullable();

            /*
             * Whether this template can be used
             * from our dashboard.
             */
            $table->boolean('is_enabled')->default(true);

            /*
             * Last successful synchronization from Meta.
             */
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            /*
             * A template name + language should be unique
             * for a particular WhatsApp number.
             */
            $table->unique([
                'whatsapp_number_id',
                'name',
                'language',
            ]);

            $table->index('template_id');
            $table->index('status');
            $table->index('category');
            $table->index('is_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};