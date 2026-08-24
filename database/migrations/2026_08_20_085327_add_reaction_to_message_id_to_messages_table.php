<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('reaction_to_message_id')
                ->nullable()
                ->after('type')
                ->constrained('messages')
                ->nullOnDelete();

            $table->index([
                'customer_id',
                'reaction_to_message_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign([
                'reaction_to_message_id',
            ]);

            $table->dropIndex([
                'messages_customer_id_reaction_to_message_id_index',
            ]);

            $table->dropColumn(
                'reaction_to_message_id'
            );
        });
    }
};