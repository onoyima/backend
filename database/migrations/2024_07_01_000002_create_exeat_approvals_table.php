<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('exeat_approvals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('exeat_request_id');
            $table->unsignedInteger('staff_id');
            $table->string('role', 50);
            $table->string('status', 20);
            $table->string('comment', 255)->nullable();
            $table->string('method', 50)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('exeat_request_id')->references('id')->on('exeat_requests');
            $table->foreign('staff_id')->references('id')->on('staff');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exeat_approvals');
    }
};
