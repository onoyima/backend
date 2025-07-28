<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('exeat_request_id');
            $table->string('file_url', 255);
            $table->unsignedBigInteger('uploaded_by'); // Can reference students or staff
            $table->timestamp('uploaded_at')->nullable();

            $table->foreign('exeat_request_id')->references('id')->on('exeat_requests');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
