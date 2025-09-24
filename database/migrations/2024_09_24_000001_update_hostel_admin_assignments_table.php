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
        Schema::table('hostel_admin_assignments', function (Blueprint $table) {
            // Add status column if it doesn't exist
            if (!Schema::hasColumn('hostel_admin_assignments', 'status')) {
                $table->string('status')->default('active')->after('assigned_at');
            }
            
            // Add index for better performance
            $table->index(['vuna_accomodation_id', 'status']);
            $table->index(['staff_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hostel_admin_assignments', function (Blueprint $table) {
            $table->dropIndex(['vuna_accomodation_id', 'status']);
            $table->dropIndex(['staff_id', 'status']);
        });
    }
};