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
        Schema::create('exeat_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exeat_request_id')->constrained('exeat_requests')->onDelete('cascade');
            $table->enum('recipient_type', ['student', 'staff', 'admin']);
            $table->unsignedBigInteger('recipient_id');
            $table->enum('notification_type', ['stage_change', 'approval_required', 'reminder', 'emergency']);
            $table->string('title');
            $table->text('message');
            $table->json('delivery_methods')->nullable(); // ['in_app', 'email', 'sms', 'whatsapp']
            $table->json('delivery_status')->nullable(); // {"email": "sent", "sms": "failed"}
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Indexes for better performance
            $table->index(['recipient_type', 'recipient_id']);
            $table->index(['exeat_request_id']);
            $table->index(['notification_type']);
            $table->index(['priority']);
            $table->index(['is_read']);
            $table->index(['created_at']);
            
            // Composite indexes for common queries
            $table->index(['recipient_type', 'recipient_id', 'is_read']);
            $table->index(['exeat_request_id', 'recipient_type']);
            $table->index(['priority', 'is_read', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exeat_notifications');
    }
};