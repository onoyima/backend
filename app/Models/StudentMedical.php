<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentMedical extends Model
{
    use HasFactory;
    protected $table = 'student_medicals';
    protected $fillable = [
        'student_id',
        'physical',
        'blood_group',
        'condition',
        'allergies',
        'genotype',
        'created_at',
        'updated_at',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
