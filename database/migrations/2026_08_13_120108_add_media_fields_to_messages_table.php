<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {

            $table->string('media_id')
                ->nullable()
                ->after('whatsapp_message_id');

            $table->string('media_mime_type')
                ->nullable()
                ->after('media_id');

            $table->string('media_filename')
                ->nullable()
                ->after('media_mime_type');

            $table->text('media_caption')
                ->nullable()
                ->after('media_filename');

            $table->json('metadata')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {

            $table->dropColumn([
                'media_id',
                'media_mime_type',
                'media_filename',
                'media_caption',
                'metadata',
            ]);
        });
    }
};