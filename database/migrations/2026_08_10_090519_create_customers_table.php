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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bitrix_lead_id')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('old_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('phone', 20)->unique();
            $table->string('email')->nullable();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
            $table->json('tags')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->unsignedBigInteger('bitrix_assigned_by_id')->nullable();
            $table->timestamp('bitrix_created_at')->nullable();
            $table->timestamp('bitrix_synced_at')->nullable();
            $table->unique('bitrix_lead_id', 'customers_bitrix_lead_id_unique');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['assigned_to', 'status']);
            $table->index('phone');
            $table->index('team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
