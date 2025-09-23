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
        'is_expired',
        'expired_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'return_date' => 'date',
        'is_medical' => 'boolean',
        'is_expired' => 'boolean',
        'expired_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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

    public function category()
    {
        return $this->belongsTo(ExeatCategory::class);
    }
    
    /**
     * Get the debts associated with this exeat request.
     */
    public function debts()
    {
        return $this->hasMany(StudentExeatDebt::class, 'exeat_request_id');
    }

    // Helper method to check if medical review is required
    public function needsMedicalReview(): bool
    {
        return $this->is_medical && $this->status === 'pending';
    }

    // Method to check if exeat request covers weekdays and send notification (called after dean approval)
    public function checkWeekdaysAndNotify(): void
    {
        $weekdaysCovered = $this->getWeekdaysCovered();
        
        if (!empty($weekdaysCovered)) {
            $this->sendWeekdayNotification($weekdaysCovered);
        }
    }

    // Get weekdays covered by the exeat request
    public function getWeekdaysCovered(): array
    {
        $departureDate = \Carbon\Carbon::parse($this->departure_date);
        $returnDate = \Carbon\Carbon::parse($this->return_date);
        $weekdays = [];
        
        $currentDate = $departureDate->copy();
        
        while ($currentDate->lte($returnDate)) {
            // Check if current date is a weekday (Monday = 1, Friday = 5)
            if ($currentDate->dayOfWeek >= 1 && $currentDate->dayOfWeek <= 5) {
                $weekdays[] = $currentDate->format('Y-m-d (l)');
            }
            $currentDate->addDay();
        }
        
        return $weekdays;
    }

    // Send email notification for weekday absence (triggered after dean approval)
    private function sendWeekdayNotification(array $weekdays): void
    {
        try {
            $student = $this->student;
            $weekdaysList = implode(', ', $weekdays);
            
            $message = "Student Weekday Absence Notification\n\n";
            $message .= "Student Name: {$student->fname} {$student->lname}\n";
            $message .= "Matric Number: {$this->matric_no}\n";
            $message .= "Reason: {$this->reason}\n";
            $message .= "Destination: {$this->destination}\n";
            $message .= "Departure Date: {$this->departure_date}\n";
            $message .= "Return Date: {$this->return_date}\n";
            $message .= "Weekdays Covered: {$weekdaysList}\n\n";
            $message .= "This student has applied to be absent during weekdays.\n\n";
            $message .= "— VERITAS University Exeat Management System";
            
            \Mail::raw($message, function ($mail) use ($student) {
                $mail->to(env('ACADEMIC_ADMIN_EMAIL'), 'Academic Administrator')
                     ->subject("Weekday Absence Alert - {$student->fname} {$student->lname} ({$this->matric_no})");
            });
            
            \Log::info('Weekday absence notification sent', [
                'exeat_request_id' => $this->id,
                'student_id' => $this->student_id,
                'matric_no' => $this->matric_no,
                'weekdays_count' => count($weekdays)
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Failed to send weekday absence notification', [
                'exeat_request_id' => $this->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Scope to get only expired exeat requests
     */
    public function scopeExpired($query)
    {
        return $query->where('is_expired', true);
    }

    /**
     * Scope to get only non-expired exeat requests
     */
    public function scopeNotExpired($query)
    {
        return $query->where('is_expired', false);
    }

    /**
     * Scope to get overdue exeat requests (past return date but not expired yet)
     */
    public function scopeOverdue($query)
    {
        return $query->where('return_date', '<', now()->toDateString())
                    ->where('is_expired', false)
                    ->whereNotIn('status', ['security_signin', 'hostel_signin', 'completed', 'rejected']);
    }

    /**
     * Check if the exeat request is overdue
     */
    public function isOverdue()
    {
        return $this->return_date < now()->toDateString() 
               && !$this->is_expired 
               && !in_array($this->status, ['security_signin', 'hostel_signin', 'completed', 'rejected']);
    }

    /**
     * Mark the exeat request as expired
     */
    public function markAsExpired()
    {
        return $this->update([
            'is_expired' => true,
            'expired_at' => now(),
            'status' => 'completed'
        ]);
    }

    /**
     * Get the status display with expired indicator
     */
    public function getStatusDisplayAttribute()
    {
        if ($this->is_expired) {
            return $this->status . ' (Expired)';
        }
        return $this->status;
    }
}

// Status enum for clarity:
// pending, cmd_review, deputy-dean_review, parent_consent, dean_review,
// hostel_signout, security_signout, security_signin, hostel_signin,
// completed, rejected, appeal
