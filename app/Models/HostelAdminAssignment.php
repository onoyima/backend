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
        'assigned_by',
        'notes',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function hostel()
    {
        return $this->belongsTo(VunaAccomodation::class, 'vuna_accomodation_id');
    }
    
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
    
    public function assignedBy()
    {
        return $this->belongsTo(Staff::class, 'assigned_by');
    }

    /**
     * Scope for active assignments only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for inactive assignments only
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Scope for assignments by hostel
     */
    public function scopeForHostel($query, $hostelId)
    {
        return $query->where('vuna_accomodation_id', $hostelId);
    }

    /**
     * Scope for assignments by staff
     */
    public function scopeForStaff($query, $staffId)
    {
        return $query->where('staff_id', $staffId);
    }
}
