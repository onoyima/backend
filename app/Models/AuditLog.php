<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;
    protected $table = 'audit_logs';
    protected $fillable = [
        'staff_id', 'student_id', 'action', 'target_type', 'target_id', 'details', 'timestamp'
    ];

    public function exeatRequest()
    {
        return $this->belongsTo(ExeatRequest::class, 'target_id')->where('target_type', 'exeat_request');
    }
    // Optionally, you can add relationships to Student or Staff if needed
}
