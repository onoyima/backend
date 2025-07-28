<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('parent_consents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('exeat_request_id');
            $table->unsignedBigInteger('student_contact_id'); // Correct type
            $table->string('consent_status', 20);
            $table->string('consent_method', 20)->nullable();
            $table->string('consent_token', 100)->nullable();
            $table->timestamp('consent_timestamp')->nullable();

            $table->foreign('exeat_request_id')->references('id')->on('exeat_requests');
            $table->foreign('student_contact_id')->references('id')->on('student_contacts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_consents');
    }
};
