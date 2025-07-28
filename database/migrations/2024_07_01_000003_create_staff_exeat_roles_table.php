<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_exeat_roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('staff_id');
            $table->unsignedBigInteger('exeat_role_id');
            $table->timestamp('assigned_at')->nullable();

            $table->foreign('staff_id')->references('id')->on('staff');
            $table->foreign('exeat_role_id')->references('id')->on('exeat_roles');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_exeat_roles');
    }
};
