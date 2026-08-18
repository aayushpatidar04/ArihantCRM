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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete(); // null = incoming from customer
            $table->foreignId('whatsapp_number_id')->nullable()->constrained('whatsapp_numbers')->nullOnDelete();
            $table->string('whatsapp_message_id')->nullable()->unique(); // from gateway
            $table->enum('direction', ['inbound', 'outbound']);
            $table->enum('type', ['text', 'image', 'document', 'audio', 'video', 'sticker', 'location', 'contact', 'chat'])->default('chat');
            $table->text('body')->nullable();
            $table->enum('status', ['pending', 'queued', 'sent', 'delivered', 'read', 'failed'])->default('pending');
            $table->string('gateway_job_id')->nullable();
            $table->text('failure_reason')->nullable();
            $table->boolean('is_forwarded')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['customer_id', 'direction', 'created_at']);
            $table->index('status');
            $table->index('team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
