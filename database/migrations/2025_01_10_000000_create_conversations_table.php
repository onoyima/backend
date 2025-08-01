<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type')->enum(['direct', 'group'])->default('direct');
            $table->string('name')->nullable(); // For group chats
            $table->text('description')->nullable(); // For group chats
            $table->string('avatar')->nullable(); // Group avatar
            $table->boolean('is_suspended')->default(false);
            $table->timestamp('suspended_at')->nullable();
            $table->unsignedBigInteger('suspended_by')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_suspended']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
