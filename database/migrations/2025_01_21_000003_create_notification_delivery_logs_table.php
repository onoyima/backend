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
        Schema::create('notification_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('exeat_notifications')->onDelete('cascade');
            $table->enum('delivery_method', ['in_app', 'email', 'sms', 'whatsapp']);
            $table->enum('delivery_status', ['pending', 'sent', 'delivered', 'failed', 'read'])->default('pending');
            $table->string('delivery_provider')->nullable(); // 'twilio', 'mailgun', 'sendgrid', 'whatsapp_business', 'internal'
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            // Indexes for better performance
            $table->index(['notification_id']);
            $table->index(['delivery_method']);
            $table->index(['delivery_status']);
            $table->index(['delivery_provider']);
            $table->index(['attempted_at']);
            $table->index(['delivered_at']);
            
            // Composite indexes for common queries
            $table->index(['notification_id', 'delivery_method']);
            $table->index(['delivery_status', 'attempted_at']);
            $table->index(['delivery_method', 'delivery_status']);
            $table->index(['delivery_provider', 'delivery_status']);
            
            // Index for retry logic
            $table->index(['delivery_status', 'attempted_at', 'provider_message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_logs');
    }
};