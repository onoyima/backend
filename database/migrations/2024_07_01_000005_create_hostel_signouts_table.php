<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hostel_signouts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('exeat_request_id');
            $table->unsignedInteger('hostel_admin_id');
            $table->timestamp('signout_time')->nullable();
            $table->timestamp('signin_time')->nullable();
            $table->string('status', 20);

            $table->foreign('exeat_request_id')->references('id')->on('exeat_requests');
            $table->foreign('hostel_admin_id')->references('id')->on('staff');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostel_signouts');
    }
};
