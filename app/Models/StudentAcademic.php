<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentAcademic extends Model
{
    use HasFactory;
    protected $table = 'student_academics';
    protected $fillable = [
        'student_id',
        'entry_mode_id',
        'study_mode_id',
        'matric_no',
        'old_matric_no',
        'course_study_id',
        'level',
        'entry_session_id',
        'vu_semester_id',
        'academic_session_id',
        'first_semester_load',
        'second_semester_load',
        'lowest_unit',
        'highest_unit',
        'summer_max',
        'program_type',
        'tc',
        'tgp',
        'jamb_no',
        'jamb_score',
        'last_registration',
        'summer',
        'studentship',
        'studentship_id',
        'admissions_type_id',
        'faculty_id',
        'department_id',
        'acad_status_id',
        'admitted_date',
        'is_hostel',
        'status',
        'created_at',
        'updated_at',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
