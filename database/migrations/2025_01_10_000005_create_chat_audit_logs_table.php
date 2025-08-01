<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->morphs('actor'); // Who performed the action
            $table->string('action'); // created, updated, deleted, suspended, etc.
            $table->string('action_type'); // message, conversation, participant, media
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('conversation_id')
                  ->references('id')
                  ->on('conversations')
                  ->onDelete('set null');

            $table->foreign('message_id')
                  ->references('id')
                  ->on('messages')
                  ->onDelete('set null');

            $table->index(['conversation_id', 'created_at']);
            $table->index(['actor_id', 'actor_type']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_audit_logs');
    }
};