<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffWorkProfile extends Model
{
    use HasFactory;
    protected $table = 'staff_work_profiles';
    protected $fillable = [
        'staff_id',
        'position_id',
        'department_id',
        'employment_type',
        'start_date',
        'end_date',
        'status',
        'created_at',
        'updated_at',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
}
