<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_read_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->morphs('reader'); // Can be Student or Staff
            $table->timestamp('read_at')->useCurrent();
            $table->timestamps();

            $table->foreign('message_id')
                  ->references('id')
                  ->on('messages')
                  ->onDelete('cascade');

            $table->unique(['message_id', 'reader_id', 'reader_type']);
            $table->index(['reader_id', 'reader_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_read_receipts');
    }
};