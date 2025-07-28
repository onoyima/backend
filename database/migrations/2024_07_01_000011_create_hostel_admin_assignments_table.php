<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hostel_admin_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('vuna_accomodation_id');
            $table->unsignedInteger('staff_id');
            $table->timestamp('assigned_at')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();

            $table->foreign('vuna_accomodation_id')->references('id')->on('vuna_accomodations');
            $table->foreign('staff_id')->references('id')->on('staff');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostel_admin_assignments');
    }
};
