<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('analytics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('metric_type', 50);
            $table->string('value', 255);
            $table->timestamp('computed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics');
    }
};
