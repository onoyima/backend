<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if deputy_dean_reason column exists before renaming
        if (Schema::hasColumn('parent_consents', 'deputy_dean_reason')) {
            Schema::table('parent_consents', function (Blueprint $table) {
                // Rename deputy_dean_reason to secretary_reason
                $table->renameColumn('deputy_dean_reason', 'secretary_reason');
            });
        }

        // Update action_type enum to include secretary options
        DB::statement("ALTER TABLE parent_consents MODIFY COLUMN action_type ENUM(
            'parent', 
            'secretary_approval', 
            'secretary_rejection'
        ) DEFAULT 'parent'");

        // Update comment for secretary_reason column
        DB::statement("ALTER TABLE parent_consents MODIFY COLUMN secretary_reason TEXT NULL COMMENT 'Reason provided by Secretary for override'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if secretary_reason column exists before renaming back
        if (Schema::hasColumn('parent_consents', 'secretary_reason')) {
            Schema::table('parent_consents', function (Blueprint $table) {
                $table->renameColumn('secretary_reason', 'deputy_dean_reason');
            });
        }

        // Revert action_type enum
        DB::statement("ALTER TABLE parent_consents MODIFY COLUMN action_type ENUM(
            'parent', 
            'deputy_dean_approval', 
            'deputy_dean_rejection'
        ) DEFAULT 'parent'");

        // Update comment back to deputy_dean_reason
        DB::statement("ALTER TABLE parent_consents MODIFY COLUMN deputy_dean_reason TEXT NULL COMMENT 'Reason provided by Deputy Dean for override'");
    }
};