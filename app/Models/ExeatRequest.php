<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExeatRequest extends Model
{
    use HasFactory;
    protected $table = 'exeat_requests';
    protected $fillable = [
        'student_id', 'category', 'reason', 'location', 'departure_date', 'return_date', 'status'
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    public function approvals()
    {
        return $this->hasMany(ExeatApproval::class);
    }
    public function parentConsents()
    {
        return $this->hasMany(ParentConsent::class);
    }
    public function hostelSignouts()
    {
        return $this->hasMany(HostelSignout::class);
    }
    public function securitySignouts()
    {
        return $this->hasMany(SecuritySignout::class);
    }
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'target_id')->where('target_type', 'exeat_request');
    }
}
