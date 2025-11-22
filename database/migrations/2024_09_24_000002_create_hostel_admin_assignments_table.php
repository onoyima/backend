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
        Schema::create('hostel_admin_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vuna_accomodation_id');
            $table->unsignedBigInteger('staff_id');
            $table->timestamp('assigned_at')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedBigInteger('assigned_by')->nullable()->comment('Staff ID who made the assignment');
            $table->text('notes')->nullable()->comment('Additional notes about the assignment');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('vuna_accomodation_id')
                  ->references('id')
                  ->on('vuna_accomodations')
                  ->onDelete('cascade');
            
            $table->foreign('staff_id')
                  ->references('id')
                  ->on('staff')
                  ->onDelete('cascade');
            
            $table->foreign('assigned_by')
                  ->references('id')
                  ->on('staff')
                  ->onDelete('set null');

            // Indexes for better performance
            $table->index(['vuna_accomodation_id', 'status'], 'hostel_status_idx');
            $table->index(['staff_id', 'status'], 'staff_status_idx');
            $table->index('assigned_at');
            $table->index('status');

            // Unique constraint to prevent duplicate active assignments
            $table->unique(['vuna_accomodation_id', 'staff_id', 'status'], 'unique_active_assignment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hostel_admin_assignments');
    }
};