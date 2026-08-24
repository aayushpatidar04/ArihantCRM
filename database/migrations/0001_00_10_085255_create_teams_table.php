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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('whatsapp_number_id')->nullable()->constrained('whatsapp_numbers')->nullOnDelete();
            $table->foreignId('parent_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('external_department_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('hierarchy_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('whatsapp_number_id');
            $table->index('external_department_id');
            $table->index('parent_team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
