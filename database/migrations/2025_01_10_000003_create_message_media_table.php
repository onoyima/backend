<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->string('filename');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->string('file_path');
            $table->string('thumbnail_path')->nullable(); // For videos/images
            $table->json('metadata')->nullable(); // Dimensions, duration, etc.
            $table->string('disk')->default('public');
            $table->timestamps();

            $table->foreign('message_id')
                  ->references('id')
                  ->on('messages')
                  ->onDelete('cascade');

            $table->index('message_id');
            $table->index('mime_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_media');
    }
};