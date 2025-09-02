<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParentConsent extends Model
{
    use HasFactory;
    protected $table = 'parent_consents';
    protected $fillable = [
        'exeat_request_id',
        'student_contact_id',
        'consent_status',
        'consent_method',
        'consent_token',
        'expires_at',
        'consent_timestamp',
        'parent_email',
        'parent_phone',
        'preferred_mode_of_contact',
        'acted_by_staff_id',
        'action_type',
        'deputy_dean_reason'
    ];

    public function exeatRequest()
    {
        return $this->belongsTo(ExeatRequest::class);
    }
    public function studentContact()
    {
        return $this->belongsTo(StudentContact::class);
    }
}
