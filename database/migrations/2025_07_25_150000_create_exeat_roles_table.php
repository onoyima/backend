<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('exeat_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('display_name', 100)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        // Seed common roles
        DB::table('exeat_roles')->insert([
            ['name' => 'dean', 'display_name' => 'Dean of Students', 'description' => 'Can approve/reject any exeat request at any stage, can override CMD or parent consent.', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'cmd', 'display_name' => 'Chief Medical Director', 'description' => 'Can vet and recommend only medical exeat requests, cannot approve.', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'deputy_dean', 'display_name' => 'Deputy Dean', 'description' => 'Can approve/reject exeat requests only after CMD (for medical) or parent has recommended/approved.', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'hostel_admin', 'display_name' => 'Hostel Admin', 'description' => 'Can sign students out/in of hostels after approval.', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'security', 'display_name' => 'Security', 'description' => 'Can sign students out/in at the gate after hostel admin.', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'admin', 'display_name' => 'Super Admin', 'description' => 'Has all permissions and can perform any action in the system.', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
    public function down(): void
    {
        Schema::dropIfExists('exeat_roles');
    }
};
