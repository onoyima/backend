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
        Schema::table('parent_consents', function (Blueprint $table) {
            // Add field to track if Deputy Dean acted on behalf of parent
            $table->foreignId('acted_by_staff_id')->nullable()->after('student_contact_id')
                  ->constrained('staff')->onDelete('set null')
                  ->comment('Staff ID if Deputy Dean acted on behalf of parent');
            
            // Add field to track the action type
            $table->enum('action_type', ['parent', 'deputy_dean'])->default('parent')->after('acted_by_staff_id')
                  ->comment('Who performed the consent action');
            
            // Add index for better performance
            $table->index(['acted_by_staff_id']);
            $table->index(['action_type']);
            $table->index(['exeat_request_id', 'action_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parent_consents', function (Blueprint $table) {
            $table->dropForeign(['acted_by_staff_id']);
            $table->dropIndex(['acted_by_staff_id']);
            $table->dropIndex(['action_type']);
            $table->dropIndex(['exeat_request_id', 'action_type']);
            $table->dropColumn(['acted_by_staff_id', 'action_type']);
        });
    }
};