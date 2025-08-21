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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->enum('user_type', ['student', 'staff']);
            $table->unsignedBigInteger('user_id');
            $table->string('notification_type')->default('all'); // 'stage_change', 'approval_required', 'reminder', 'emergency', 'all'
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('whatsapp_enabled')->default(false);
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->timestamps();

            // Unique constraint to prevent duplicate preferences
            $table->unique(['user_type', 'user_id', 'notification_type']);
            
            // Indexes for better performance
            $table->index(['user_type', 'user_id']);
            $table->index(['notification_type']);
            $table->index(['in_app_enabled']);
            $table->index(['email_enabled']);
            $table->index(['sms_enabled']);
            $table->index(['whatsapp_enabled']);
            
            // Composite indexes for common queries
            $table->index(['user_type', 'user_id', 'notification_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};