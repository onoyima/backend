<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->morphs('sender'); // Can be Student or Staff
            $table->text('content')->nullable();
            $table->string('type')->default('text'); // text, image, video, file
            $table->json('metadata')->nullable(); // For media info, reactions, etc.
            $table->string('status')->default('sent'); // sent, delivered, read
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->unsignedBigInteger('reply_to_id')->nullable();
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('conversation_id')
                  ->references('id')
                  ->on('conversations')
                  ->onDelete('cascade');

            $table->foreign('reply_to_id')
                  ->references('id')
                  ->on('messages')
                  ->onDelete('set null');

            $table->index(['conversation_id', 'created_at']);
            $table->index(['sender_id', 'sender_type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};