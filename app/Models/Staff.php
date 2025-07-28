<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Staff extends Model
{
    use HasApiTokens, HasFactory;
    protected $table = 'staff';
    protected $fillable = [
        'user_type',
        'fname',
        'lname',
        'mname',
        'maiden_name',
        'dob',
        'title',
        'country_id',
        'state_id',
        'lga_name',
        'address',
        'city',
        'religion',
        'phone',
        'p_email',
        'marital_status',
        'gender',
        'passport',
        'signature',
        'email',
        'password',
        'status',
        'created_at',
        'updated_at',
        'remember_token',
    ];

    public function assignedCourses()
    {
        return $this->hasMany(StaffAssignedCourse::class, 'staff_id');
    }
    public function contacts()
    {
        return $this->hasMany(StaffContact::class, 'staff_id');
    }
    public function workProfiles()
    {
        return $this->hasMany(StaffWorkProfile::class, 'staff_id');
    }
    public function assignedRoles()
    {
        return $this->hasMany(StaffAssignedRole::class, 'staff_id');
    }
    public function staffCourses()
    {
        return $this->hasMany(StaffCourse::class, 'staff_id');
    }
    public function employmentCategories()
    {
        return $this->hasMany(StaffEmploymentCategory::class, 'staff_id');
    }
    public function leaveSummaries()
    {
        return $this->hasMany(StaffLeaveSummary::class, 'staff_id');
    }
    public function leaves()
    {
        return $this->hasMany(StaffLeave::class, 'staff_id');
    }
    public function positions()
    {
        return $this->hasMany(StaffPosition::class, 'staff_id');
    }
    public function promotions()
    {
        return $this->hasMany(StaffPromotion::class, 'staff_id');
    }
    public function steps()
    {
        return $this->hasMany(StaffStep::class, 'staff_id');
    }
    public function typeSummaries()
    {
        return $this->hasMany(StaffTypeSummary::class, 'staff_id');
    }
    public function types()
    {
        return $this->hasMany(StaffType::class, 'staff_id');
    }
    public function exeatRoles()
    {
        return $this->hasMany(StaffExeatRole::class, 'staff_id');
    }
    public function hostelSignouts()
    {
        return $this->hasMany(HostelSignout::class, 'hostel_admin_id');
    }
    public function securitySignouts()
    {
        return $this->hasMany(SecuritySignout::class, 'security_id');
    }
    public function exeatApprovals()
    {
        return $this->hasMany(ExeatApproval::class, 'staff_id');
    }
    public function hostelsManaged()
    {
        return $this->hasMany(HostelAdminAssignment::class, 'staff_id');
    }
    public function staffMedicals()
    {
        return $this->hasMany(StaffMedical::class, 'staff_id');
    }
}
