<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('exeat_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->string('category', 50);
            $table->string('reason', 255);
            $table->string('location', 255)->nullable();
            $table->date('departure_date');
            $table->date('return_date');
            $table->string('status', 50);
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exeat_requests');
    }
};
