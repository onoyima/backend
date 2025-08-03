<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    protected $fillable = [
        'staff_id',
        'student_id',
        'action',
        'target_type',
        'target_id',
        'details',
        'timestamp'
    ];

    public function exeatRequest()
    {
        return $this->belongsTo(ExeatRequest::class, 'target_id')->where('target_type', 'exeat_request');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
