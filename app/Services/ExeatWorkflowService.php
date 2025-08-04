<?php

namespace App\Services;

use App\Models\ExeatRequest;
use App\Models\ExeatApproval;
use App\Models\ParentConsent;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;
use Carbon\Carbon;

class ExeatWorkflowService
{
    public function approve(ExeatRequest $exeatRequest, ExeatApproval $approval, $comment = null)
    {
        $oldStatus = $exeatRequest->status;

        $approval->status = 'approved';
        $approval->comment = $comment;
        $approval->save();

        $this->advanceStage($exeatRequest);

        $this->createAuditLog(
            $exeatRequest,
            $approval->staff_id,
            $exeatRequest->student_id,
            'approve',
            "Status changed from {$oldStatus} to {$exeatRequest->status}",
            $comment
        );

        Log::info('WorkflowService: Exeat approved', ['exeat_id' => $exeatRequest->id, 'approval_id' => $approval->id]);

        return $exeatRequest;
    }

    public function reject(ExeatRequest $exeatRequest, ExeatApproval $approval, $comment = null)
    {
        $oldStatus = $exeatRequest->status;

        $approval->status = 'rejected';
        $approval->comment = $comment;
        $approval->save();

        $exeatRequest->status = 'rejected';
        $exeatRequest->save();

        $this->createAuditLog(
            $exeatRequest,
            $approval->staff_id,
            $exeatRequest->student_id,
            'reject',
            "Status changed from {$oldStatus} to rejected",
            $comment
        );

        Log::info('WorkflowService: Exeat rejected', ['exeat_id' => $exeatRequest->id, 'approval_id' => $approval->id]);

        return $exeatRequest;
    }

  protected function advanceStage(ExeatRequest $exeatRequest)
    {
        $oldStatus = $exeatRequest->status;

        switch ($exeatRequest->status) {
            case 'pending':
                $exeatRequest->status = $exeatRequest->is_medical ? 'cmd_review' : 'deputy-dean_review';
                break;
            case 'cmd_review':
                $exeatRequest->status = 'deputy-dean_review';
                break;
            case 'deputy-dean_review':
                $exeatRequest->status = 'parent_consent';
                break;
            case 'parent_consent':
                $exeatRequest->status = 'dean_review';
                break;
            case 'dean_review':
                $exeatRequest->status = 'hostel_signout';
                break;
            case 'hostel_signout':
                $exeatRequest->status = 'security_signout';
                break;
            case 'security_signout':
                $exeatRequest->status = 'security_signin';
                break;
            case 'security_signin':
                $exeatRequest->status = 'hostel_signin';
                break;
            case 'hostel_signin':
                $exeatRequest->status = 'completed';
                break;
            default:
                Log::warning("WorkflowService: Unknown or final status {$exeatRequest->status} for ExeatRequest ID {$exeatRequest->id}");
                return;
        }
        $this->notifyStudentStatusChange($exeatRequest);
        $exeatRequest->save();

        // ✅ Automatically trigger parent consent mail
    if ($exeatRequest->status === 'parent_consent') {
        $staffId = $exeatRequest->approvals()->latest()->first()->staff_id ?? null;

            $this->sendParentConsent($exeatRequest, $exeatRequest->preferred_mode_of_contact ?? 'email', null, $staffId);
        }

        Log::info('WorkflowService: Exeat advanced to next stage', [
            'exeat_id' => $exeatRequest->id,
            'old_status' => $oldStatus,
            'new_status' => $exeatRequest->status,
        ]);
    }

    public function sendParentConsent(ExeatRequest $exeatRequest, string $method, ?string $message = null, ?int $staffId = null)
    {
        $exeatRequest->loadMissing('student');
        $oldStatus = $exeatRequest->status;

        // Set expiration for 24 hours from now
        $expiresAt = Carbon::now()->addHours(24);

        $parentConsent = ParentConsent::updateOrCreate(
            ['exeat_request_id' => $exeatRequest->id],
            [
                'consent_status'    => 'pending',
                'consent_method'    => $method,
                'consent_token'     => uniqid('consent_', true),
                'consent_message'   => $message,
                'consent_timestamp' => null,
                'expires_at'        => $expiresAt,
            ]
        );

        $student      = $exeatRequest->student;
        $parentEmail  = $exeatRequest->parent_email;
        $parentPhone  = $exeatRequest->parent_phone_no;
        $studentName  = $student ? "{$student->fname} {$student->lname}" : '';
        $reason       = $exeatRequest->reason;

        $linkApprove  = url('/api/parent/exeat-consent/'.$parentConsent->consent_token.'/approve');
        $linkReject   = url('/api/parent/exeat-consent/'.$parentConsent->consent_token.'/reject');

        $expiryText = $expiresAt->format('F j, Y g:i A');

        $notificationEmail = <<<EOD
    Hello,

    We would like to inform you that $studentName has requested permission to leave campus due to the following reason: "$reason".

    Please review and provide your consent before $expiryText:

    Approve: $linkApprove  
    Reject: $linkReject

    Thank you for your support.

    — VERITAS University Exeat Management Team
    EOD;

        $notificationSMS = "Dear Parent of $studentName, reason: \"$reason\".\nApprove: $linkApprove\nReject: $linkReject\nValid until: $expiryText";

        try {
            \Mail::raw($notificationEmail, fn($msg) => $msg->to($parentEmail)->subject('Exeat Consent Request'));
            \Mail::raw($notificationEmail, fn($msg) => $msg->to('onoyimab@veritas.edu.ng')->subject('Exeat Consent Request'));
            // Uncomment to enable SMS/WhatsApp notifications for parent consent:
            // if ($method === 'text') {
            //     $this->sendSmsOrWhatsapp($parentPhone, $notificationSMS, 'sms');
            // } else if ($method === 'whatsapp') {
            //     $this->sendSmsOrWhatsapp($parentPhone, $notificationSMS, 'whatsapp');
            // }
        } catch (\Exception $e) {
            Log::error('Email failed', ['error' => $e->getMessage()]);
        }

        Log::info('Parent consent requested', [
            'exeat_id' => $exeatRequest->id,
            'method' => $method,
            'parent_email' => $parentEmail,
            'parent_phone' => $parentPhone,
            'expires_at' => $expiryText
        ]);

        $exeatRequest->status = 'parent_consent';
        $exeatRequest->save();

        if ($staffId) {
            $this->createAuditLog(
                $exeatRequest,
                $staffId,
                $exeatRequest->student_id,
                'parent_consent_request',
                "Changed from {$oldStatus} to parent_consent",
                "Method: {$method}"
            );
        }

        return $parentConsent;
    }
    public function parentConsentApprove(ParentConsent $parentConsent)
    {
        $parentConsent->consent_status = 'approved';
        $parentConsent->consent_timestamp = now();
        $parentConsent->save();

        $exeatRequest = $parentConsent->exeatRequest;
        $oldStatus = $exeatRequest->status;
        $exeatRequest->status = 'dean_review';
        $exeatRequest->save();
        $this->notifyStudentStatusChange($exeatRequest);

        $this->createAuditLog(
            $exeatRequest,
            null,
            $exeatRequest->student_id,
            'parent_consent_approve',
            "Status changed from {$oldStatus} to dean_review",
            "Parent approved consent request"
        );

        Log::info('WorkflowService: Parent consent approved', [
            'exeat_id' => $exeatRequest->id,
            'parent_consent_id' => $parentConsent->id,
        ]);

        return $exeatRequest;
    }

    public function parentConsentDecline(ParentConsent $parentConsent)
    {
        $parentConsent->consent_status = 'declined';
        $parentConsent->consent_timestamp = now();
        $parentConsent->save();

        $exeatRequest = $parentConsent->exeatRequest;
        $oldStatus = $exeatRequest->status;
        $exeatRequest->status = 'rejected';
        $exeatRequest->save();
        $this->notifyStudentStatusChange($exeatRequest);

        $this->createAuditLog(
            $exeatRequest,
            null,
            $exeatRequest->student_id,
            'parent_consent_decline',
            "Status changed from {$oldStatus} to rejected",
            "Parent declined consent request"
        );

        Log::info('WorkflowService: Parent consent declined', [
            'exeat_id' => $exeatRequest->id,
            'parent_consent_id' => $parentConsent->id,
        ]);

        return $exeatRequest;
    }

    protected function createAuditLog(ExeatRequest $exeatRequest, ?int $staffId, ?int $studentId, string $action, string $details, ?string $comment = null)
    {
        $logDetails = $details;
        if ($comment) {
            $logDetails .= " | Comment: {$comment}";
        }

        $auditLog = AuditLog::create([
            'staff_id' => $staffId,
            'student_id' => $studentId,
            'action' => $action,
            'target_type' => 'exeat_request',
            'target_id' => $exeatRequest->id,
            'details' => $logDetails,
            'timestamp' => now(),
        ]);

        Log::info('WorkflowService: Created audit log', [
            'exeat_id' => $exeatRequest->id,
            'audit_log_id' => $auditLog->id,
            'action' => $action
        ]);

        return $auditLog;
    }

    protected function sendSmsOrWhatsapp(string $to, string $message, string $channel)
    {
        $client = new Client(config('services.twilio.sid'), config('services.twilio.token'));
        $from   = $channel === 'whatsapp'
                    ? 'whatsapp:' . config('services.twilio.whatsapp_from')
                    : config('services.twilio.sms_from');
        $toPref = $channel === 'whatsapp' ? 'whatsapp:' . $to : $to;

        try {
            $client->messages->create($toPref, [
                'from' => $from,
                'body' => $message,
            ]);
            \Log::info("Sent $channel message to $to");
        } catch (\Exception $e) {
            \Log::error("Failed to send $channel message to $to", ['error' => $e->getMessage()]);
        }
    }

    protected function triggerVoiceCall(string $to, string $message)
    {
    // Optional: Replace this with Twilio Voice API
    \Log::info("Simulated call to $to with message: $message");
}


protected function notifyStudentStatusChange(ExeatRequest $exeatRequest)
{
    $student = $exeatRequest->student;

    if (!$student || !$student->username) {
        \Log::warning("No email available for student ID {$exeatRequest->student_id}");
        return;
    }

    $message = <<<EOT
Dear {$student->fname} {$student->lname},

Your exeat request status has changed.

Current status: {$exeatRequest->status}
Reason: {$exeatRequest->reason}

Thank you.

— VERITAS University Exeat Management System
EOT;

    try {
        \Mail::raw($message, function ($msg) use ($student) {
            $msg->to($student->username) // Email is stored in 'username' field
                ->subject('Exeat Request Status Updated');
        });
    } catch (\Exception $e) {
        \Log::error('Failed to send status update email', ['error' => $e->getMessage()]);
    }
}


}
