<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentExeatDebt extends Model
{
    use HasFactory;

    protected $table = 'student_exeat_debts';

    protected $fillable = [
        'student_id',
        'exeat_request_id',
        'amount',
        'processing_charge',
        'total_amount_with_charge',
        'overdue_hours',
        'payment_status',
        'payment_reference',
        'payment_proof',
        'payment_date',
        'cleared_by',
        'cleared_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processing_charge' => 'decimal:2',
        'total_amount_with_charge' => 'decimal:2',
        'overdue_hours' => 'integer',
        'payment_date' => 'datetime',
        'cleared_at' => 'datetime',
    ];

    /**
     * Get the student that owns the debt.
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the exeat request that caused the debt.
     */
    public function exeatRequest()
    {
        return $this->belongsTo(ExeatRequest::class);
    }

    /**
     * Get the staff member who cleared the debt.
     */
    public function clearedByStaff()
    {
        return $this->belongsTo(Staff::class, 'cleared_by');
    }

    /**
     * Check if the debt has been paid but not yet cleared.
     */
    public function isPaidButNotCleared(): bool
    {
        return $this->payment_status === 'paid' && $this->cleared_by === null;
    }

    /**
     * Check if the debt has been fully cleared.
     */
    public function isCleared(): bool
    {
        return $this->payment_status === 'cleared' && $this->cleared_by !== null;
    }
}