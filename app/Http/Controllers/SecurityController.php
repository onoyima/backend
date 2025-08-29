<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SecuritySignout;
use App\Models\ExeatRequest;
use App\Services\ExeatNotificationService;
use App\Services\ExeatWorkflowService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SecurityController extends Controller
{
    protected $notificationService;
    protected $workflowService;

    public function __construct(ExeatNotificationService $notificationService, ExeatWorkflowService $workflowService)
    {
        $this->notificationService = $notificationService;
        $this->workflowService = $workflowService;
        $this->middleware('auth:sanctum');
    }
    // POST /api/security/validate
    public function validateStudent(Request $request)
    {
        $validated = $request->validate([
            'qr_code' => 'nullable|string',
            'matric_number' => 'nullable|string',
        ]);
        // For demo: find by QR or matric
        $exeat = null;
        if (!empty($validated['qr_code'])) {
            $exeat = ExeatRequest::with('student:id,fname,lname,passport')
                ->whereRaw('CONCAT("QR-", id, "-", student_id) = ?', [$validated['qr_code']])
                ->first();
        } elseif (!empty($validated['matric_number'])) {
            $exeat = ExeatRequest::with('student:id,fname,lname,passport')
                ->whereHas('student', function($q) use ($validated) {
                    $q->where('matric_number', $validated['matric_number']);
                })
                ->where('status', 'approved')
                ->first();
        }
        if (!$exeat) {
            return response()->json(['message' => 'No valid exeat found.'], 404);
        }
        Log::info('Security validated student', ['exeat_id' => $exeat->id]);
        return response()->json(['message' => 'Student validated.', 'exeat_request' => $exeat]);
    }

    // Note: Security signout/signin is now handled through StaffExeatRequestController approve method
    
    // Note: Parent notifications are now handled in ExeatWorkflowService
}
