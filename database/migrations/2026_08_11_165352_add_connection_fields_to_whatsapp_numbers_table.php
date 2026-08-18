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
        Schema::table('whatsapp_numbers', function (Blueprint $table) {
            $table->string('quality_rating')->nullable()->after('verified_name');
            $table->string('code_verification_status')->nullable()->after('quality_rating');
            $table->timestamp('last_connection_check_at')->nullable()->after('last_connected_at');
            $table->text('last_connection_error')->nullable()->after('last_connection_check_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_numbers', function (Blueprint $table) {
            //
        });
    }
};
