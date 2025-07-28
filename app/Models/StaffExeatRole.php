<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * staff_exeat_roles links staff to exeat_roles (dean, deputy_dean, cmd, etc.)
 * Used to enforce workflow and permissions.
 */
class StaffExeatRole extends Model
{
    use HasFactory;
    protected $table = 'staff_exeat_roles';
    protected $fillable = [
        'staff_id', 'exeat_role_id', 'assigned_at',  'updated_at','created_at'
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function role()
    {
        return $this->belongsTo(ExeatRole::class, 'exeat_role_id');
    }
}
