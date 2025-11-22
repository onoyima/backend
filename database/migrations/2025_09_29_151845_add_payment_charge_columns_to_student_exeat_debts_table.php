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
        Schema::table('student_exeat_debts', function (Blueprint $table) {
            $table->decimal('processing_charge', 10, 2)->nullable()->after('amount');
            $table->decimal('total_amount_with_charge', 10, 2)->nullable()->after('processing_charge');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_exeat_debts', function (Blueprint $table) {
            $table->dropColumn(['processing_charge', 'total_amount_with_charge']);
        });
    }
};
