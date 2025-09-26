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
        // Update exeat_roles table
        DB::table('exeat_roles')
            ->where('name', 'deputy_dean')
            ->update([
                'name' => 'secretary',
                'display_name' => 'Secretary',
                'description' => 'Can approve/reject exeat requests and act on behalf of parents when needed.',
                'updated_at' => now()
            ]);

        // Update existing staff role assignments
        // No changes needed to staff_exeat_roles table as it references exeat_roles.id
        
        // Update parent consent action types
        DB::table('parent_consents')
            ->where('action_type', 'deputy_dean_approval')
            ->update(['action_type' => 'secretary_approval']);
            
        DB::table('parent_consents')
            ->where('action_type', 'deputy_dean_rejection')
            ->update(['action_type' => 'secretary_rejection']);

        // Update existing exeat requests status
        DB::table('exeat_requests')
            ->where('status', 'deputy-dean_review')
            ->update(['status' => 'secretary_review']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse the changes
        DB::table('exeat_roles')
            ->where('name', 'secretary')
            ->update([
                'name' => 'deputy_dean',
                'display_name' => 'Deputy Dean',
                'description' => 'Can approve/reject exeat requests only after CMD (for medical) or parent has recommended/approved.',
                'updated_at' => now()
            ]);

        DB::table('parent_consents')
            ->where('action_type', 'secretary_approval')
            ->update(['action_type' => 'deputy_dean_approval']);
            
        DB::table('parent_consents')
            ->where('action_type', 'secretary_rejection')
            ->update(['action_type' => 'deputy_dean_rejection']);

        DB::table('exeat_requests')
            ->where('status', 'secretary_review')
            ->update(['status' => 'deputy-dean_review']);
    }
};