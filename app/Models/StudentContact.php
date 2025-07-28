<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentContact extends Model
{
    use HasFactory;
    protected $table = 'student_contacts';
    protected $fillable = [
        'student_id',
        'title',
        'surname',
        'other_names',
        'relationship',
        'address',
        'state',
        'city',
        'phone_no',
        'phone_no_two',
        'email',
        'email_two',
        'status',
        'created_at',
        'updated_at',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
