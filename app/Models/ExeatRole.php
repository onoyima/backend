<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExeatRole extends Model
{
    use HasFactory;
    protected $table = 'exeat_roles';
    protected $fillable = [
        'name', 'display_name', 'description'
    ];

    public function staffAssignments()
    {
        return $this->hasMany(StaffExeatRole::class, 'exeat_role_id');
    }
} 