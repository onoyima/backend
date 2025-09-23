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
            if (!Schema::hasColumn('student_exeat_debts', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->after('payment_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_exeat_debts', function (Blueprint $table) {
            if (Schema::hasColumn('student_exeat_debts', 'payment_reference')) {
                $table->dropColumn('payment_reference');
            }
        });
    }
};