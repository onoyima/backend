<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Add indexes to speed up Fast-Track Gate Control searches
     */
    public function up(): void
    {
        // Use raw SQL with IF NOT EXISTS to safely create indexes
        // This works on MySQL/MariaDB without requiring Doctrine

        DB::statement('CREATE INDEX IF NOT EXISTS idx_exeat_requests_status ON exeat_requests(status)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_exeat_requests_matric_no ON exeat_requests(matric_no)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_exeat_requests_status_updated_at ON exeat_requests(status, updated_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_exeat_requests_student_id ON exeat_requests(student_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_exeat_requests_departure_date ON exeat_requests(departure_date)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_exeat_requests_return_date ON exeat_requests(return_date)');

        DB::statement('CREATE INDEX IF NOT EXISTS idx_students_fname ON students(fname)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_students_lname ON students(lname)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_students_mname ON students(mname)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes if they exist
        DB::statement('DROP INDEX IF EXISTS idx_exeat_requests_status ON exeat_requests');
        DB::statement('DROP INDEX IF EXISTS idx_exeat_requests_matric_no ON exeat_requests');
        DB::statement('DROP INDEX IF EXISTS idx_exeat_requests_status_updated_at ON exeat_requests');
        DB::statement('DROP INDEX IF EXISTS idx_exeat_requests_student_id ON exeat_requests');
        DB::statement('DROP INDEX IF EXISTS idx_exeat_requests_departure_date ON exeat_requests');
        DB::statement('DROP INDEX IF EXISTS idx_exeat_requests_return_date ON exeat_requests');

        DB::statement('DROP INDEX IF EXISTS idx_students_fname ON students');
        DB::statement('DROP INDEX IF EXISTS idx_students_lname ON students');
        DB::statement('DROP INDEX IF EXISTS idx_students_mname ON students');
    }
};
