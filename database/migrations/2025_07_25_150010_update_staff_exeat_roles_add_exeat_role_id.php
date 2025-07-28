<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('staff_exeat_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('exeat_role_id')->nullable()->after('staff_id');
            $table->foreign('exeat_role_id')->references('id')->on('exeat_roles')->onDelete('cascade');
            // Optionally drop the old exeat_role column if it exists
            if (Schema::hasColumn('staff_exeat_roles', 'exeat_role')) {
                $table->dropColumn('exeat_role');
            }
        });
    }
    public function down(): void
    {
        Schema::table('staff_exeat_roles', function (Blueprint $table) {
            $table->dropForeign(['exeat_role_id']);
            $table->dropColumn('exeat_role_id');
            // Optionally add back the exeat_role column
            $table->string('exeat_role')->nullable();
        });
    }
}; 