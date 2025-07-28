<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('staff_id')->nullable(); // Can reference students or staff
            $table->unsignedBigInteger('student_id')->nullable(); // Can reference students or staff
            $table->string('action', 100);
            $table->string('target_type', 50);
            $table->unsignedBigInteger('target_id');
            $table->text('details')->nullable();
            $table->timestamp('timestamp')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
