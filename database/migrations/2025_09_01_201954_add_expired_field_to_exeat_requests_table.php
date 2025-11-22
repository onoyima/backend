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
        Schema::table('exeat_requests', function (Blueprint $table) {
            $table->boolean('is_expired')->default(false)->after('is_medical');
            $table->timestamp('expired_at')->nullable()->after('is_expired');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exeat_requests', function (Blueprint $table) {
            $table->dropColumn(['is_expired', 'expired_at']);
        });
    }
};
