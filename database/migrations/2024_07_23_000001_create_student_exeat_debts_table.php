<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_exeat_debts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('exeat_request_id')->constrained('exeat_requests');

            $table->decimal('amount', 10, 2)->default(0);
            $table->integer('overdue_hours')->default(0);

            $table->string('payment_status')->default('unpaid'); // unpaid, paid, cleared
            $table->string('payment_reference')->nullable();
            $table->text('payment_proof')->nullable(); // Could store file path or description
            $table->timestamp('payment_date')->nullable();

            // ✅ Match staff.id type: INT UNSIGNED
            $table->unsignedInteger('cleared_by')->nullable();
            $table->foreign('cleared_by')->references('id')->on('staff')->onDelete('set null');

            $table->timestamp('cleared_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_exeat_debts');
    }
};
