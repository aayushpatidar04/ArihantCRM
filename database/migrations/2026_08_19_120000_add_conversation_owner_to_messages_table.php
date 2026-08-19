<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('conversation_user_id')->nullable()->after('sent_by')->constrained('users')->nullOnDelete();
            $table->foreignId('conversation_team_id')->nullable()->after('conversation_user_id')->constrained('teams')->nullOnDelete();
            $table->index(['customer_id', 'conversation_user_id']);
        });

        DB::table('messages')->whereNotNull('sent_by')->update([
            'conversation_user_id' => DB::raw('sent_by'),
            'conversation_team_id' => DB::raw('team_id'),
        ]);

        DB::table('messages')->whereNull('conversation_user_id')->where('direction', 'inbound')->orderBy('id')->chunkById(500, function ($messages): void {
            foreach ($messages as $message) {
                $customer = DB::table('customers')->select(['assigned_to', 'team_id'])->where('id', $message->customer_id)->first();

                if (!$customer || !$customer->assigned_to) {
                    continue;
                }

                DB::table('messages')->where('id', $message->id)->update([
                    'conversation_user_id' => $customer->assigned_to,
                    'conversation_team_id' => $customer->team_id,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['conversation_user_id']);
            $table->dropForeign(['conversation_team_id']);
            $table->dropIndex(['customer_id', 'conversation_user_id']);
            $table->dropColumn(['conversation_user_id', 'conversation_team_id']);
        });
    }
};