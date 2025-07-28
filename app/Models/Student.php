<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Student extends Model
{
    use HasApiTokens, HasFactory;

    protected $table = 'students';

    protected $fillable = [
        'user_id',
        'user_type',
        'student_role_id',
        'email',
        'password',
        'title_id',
        'lname',
        'fname',
        'mname',
        'gender',
        'dob',
        'country_id',
        'state_id',
        'lga_name',
        'city',
        'religion',
        'marital_status',
        'address',
        'phone',
        'username',
        'passport',
        'signature',
        'hobbies',
        'email_verified_at',
        'status',
        'remember_token',
        'created_at',
        'updated_at',
    ];

    public function academics()
    {
        return $this->hasMany(StudentAcademic::class, 'student_id');
    }

    public function contacts()
    {
        return $this->hasMany(StudentContact::class, 'student_id');
    }

    // Existing relationship
    public function medicals()
    {
        return $this->hasMany(StudentMedical::class, 'student_id');
    }



    public function roleUsers()
    {
        return $this->hasMany(StudentRoleUser::class, 'user_id');
    }

    public function accomodationHistories()
    {
        return $this->hasMany(VunaAccomodationHistory::class, 'student_id');
    }

    public function exeatRequests()
    {
        return $this->hasMany(ExeatRequest::class, 'student_id');
    }
}
