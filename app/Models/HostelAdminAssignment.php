<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HostelAdminAssignment extends Model
{
    use HasFactory;
    protected $table = 'hostel_admin_assignments';
    protected $fillable = [
        'vuna_accomodation_id',
        'staff_id',
        'assigned_at',
        'status',
        'created_at',
        'updated_at',
    ];

    public function hostel()
    {
        return $this->belongsTo(VunaAccomodation::class, 'vuna_accomodation_id');
    }
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
}
