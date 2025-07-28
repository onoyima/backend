<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentRoleUser extends Model
{
    use HasFactory;
    protected $table = 'student_role_users';
    protected $fillable = [
        'user_id',
        'student_role_id',
        'status',
        'created_at',
        'updated_at',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'user_id');
    }
}
