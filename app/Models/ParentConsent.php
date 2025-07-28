<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParentConsent extends Model
{
    use HasFactory;
    protected $table = 'parent_consents';
    protected $fillable = [
        'exeat_request_id', 'student_contact_id', 'consent_status', 'consent_method', 'consent_token', 'consent_timestamp'
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
