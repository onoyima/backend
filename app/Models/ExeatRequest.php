<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExeatRequest extends Model
{
    use HasFactory;

    protected $table = 'exeat_requests';

    protected $fillable = [
        'student_id',
        'matric_no',
        'category_id',
        'reason',
        'destination',
        'departure_date',
        'return_date',
        'preferred_mode_of_contact',
        'parent_surname',
        'parent_othernames',
        'parent_phone_no',
        'parent_phone_no_two',
        'parent_email',
        'student_accommodation',
        'status',
        'is_medical',
        'created_at',
        'updated_at',
    ];

    // Relationships (unchanged) ...
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

    // Helper method to check if medical review is required
    public function needsMedicalReview(): bool
    {
        return $this->is_medical && $this->status === 'pending';
    }
}

// Status enum for clarity:
// pending, medical_review, recommendation1, parent_pending, dean_final_pending,
// hostel_signout, security_signout, security_signin, hostel_signin,
// completed, rejected, appeal
