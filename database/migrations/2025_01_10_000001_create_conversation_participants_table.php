<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->morphs('participant'); // Can be Student or Staff
            $table->string('role')->default('member'); // member, admin, creator
            $table->boolean('can_add_members')->default(false);
            $table->boolean('can_remove_members')->default(false);
            $table->boolean('can_edit_group')->default(false);
            $table->boolean('can_delete_messages')->default(false);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('conversation_id')
                  ->references('id')
                  ->on('conversations')
                  ->onDelete('cascade');

            $table->unique(['conversation_id', 'participant_id', 'participant_type']);
            $table->index(['participant_id', 'participant_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
    }
};