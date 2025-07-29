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
            $table->string('matric_no', 50)->nullable();
            $table->unsignedBigInteger('category_id');
            $table->string('reason', 255);
            $table->string('destination', 255);
            $table->date('departure_date');
            $table->date('return_date');
            $table->enum('preferred_mode_of_contact', ['whatsapp', 'text', 'phone_call', 'any']);
            $table->string('parent_surname', 100)->nullable();
            $table->string('parent_othernames', 100)->nullable();
            $table->string('parent_phone_no', 30)->nullable();
            $table->string('parent_phone_no_two', 30)->nullable();
            $table->string('parent_email', 100)->nullable();
            $table->string('student_accommodation', 100)->nullable();
            $table->enum('status', ['pending', 'medical_review', 'recommendation1', 'parent_pending', 'dean_final_pending', 'hostel_signout', 'security_signout', 'security_signin', 'hostel_signin', 'completed', 'rejected', 'appeal'])->default('pending');
$table->boolean('is_medical')->default(false);
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students');
            $table->foreign('category_id')->references('id')->on('exeat_categories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exeat_requests');
    }
};
