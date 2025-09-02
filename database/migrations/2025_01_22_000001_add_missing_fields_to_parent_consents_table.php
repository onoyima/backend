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
            // Add missing fields that were referenced in the model but not in database
            $table->string('parent_email', 100)->nullable()->after('student_contact_id')
                  ->comment('Parent email address for consent');
            
            $table->string('parent_phone', 30)->nullable()->after('parent_email')
                  ->comment('Parent phone number for consent');
            
            $table->string('preferred_mode_of_contact', 20)->nullable()->after('parent_phone')
                  ->comment('Preferred contact method (email, text, whatsapp)');
            
            $table->text('deputy_dean_reason')->nullable()->after('preferred_mode_of_contact')
                  ->comment('Reason provided by deputy dean when acting on behalf of parent');
            
            $table->timestamp('expires_at')->nullable()->after('deputy_dean_reason')
                  ->comment('When the consent request expires');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parent_consents', function (Blueprint $table) {
            $table->dropColumn([
                'parent_email',
                'parent_phone', 
                'preferred_mode_of_contact',
                'deputy_dean_reason',
                'expires_at'
            ]);
        });
    }
};