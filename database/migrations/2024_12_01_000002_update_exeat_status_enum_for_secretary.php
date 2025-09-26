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
        // Alter the enum to replace deputy-dean_review with secretary_review
        DB::statement("ALTER TABLE exeat_requests MODIFY COLUMN status ENUM(
            'pending', 
            'cmd_review', 
            'secretary_review',
            'parent_consent', 
            'dean_review', 
            'hostel_signout', 
            'security_signout', 
            'security_signin', 
            'hostel_signin', 
            'completed', 
            'rejected', 
            'appeal'
        ) DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse the enum change
        DB::statement("ALTER TABLE exeat_requests MODIFY COLUMN status ENUM(
            'pending', 
            'cmd_review', 
            'deputy-dean_review',
            'parent_consent', 
            'dean_review', 
            'hostel_signout', 
            'security_signout', 
            'security_signin', 
            'hostel_signin', 
            'completed', 
            'rejected', 
            'appeal'
        ) DEFAULT 'pending'");
    }
};