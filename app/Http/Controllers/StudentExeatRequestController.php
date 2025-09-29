<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\ExeatRequest;
use Illuminate\Support\Facades\Storage;
use App\Models\ExeatCategory;
use App\Models\StudentAcademic;
use App\Models\StudentContact;
use App\Models\VunaAccomodationHistory;
use App\Models\VunaAccomodation;
use App\Models\AuditLog;
use App\Models\ExeatApproval;
use App\Services\ExeatNotificationService;
use App\Models\ExeatNotification;
use App\Models\StudentExeatDebt;

class StudentExeatRequestController extends Controller
{
    protected $notificationService;

    public function __construct(ExeatNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        $this->middleware('auth:sanctum');
    }
    // POST /api/student/exeat-requests
    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'category_id' => 'required|integer|exists:exeat_categories,id',
            'reason' => 'required|string',
            'destination' => 'required|string',
            'departure_date' => 'required|date',
            'return_date' => 'required|date|after_or_equal:departure_date',
            'preferred_mode_of_contact' => 'required|in:whatsapp,text,phone_call,any',
        ]);

        // Check for unpaid exeat debts
        $unpaidDebts = \App\Models\StudentExeatDebt::where('student_id', $user->id)
            ->whereIn('payment_status', ['unpaid', 'paid']) // Include 'paid' but not yet cleared
            ->with('exeatRequest:id,departure_date,return_date')
            ->get();

        if ($unpaidDebts->count() > 0) {
            $totalDebt = $unpaidDebts->sum('amount');
            $debtDetails = $unpaidDebts->map(function ($debt) {
                return [
                    'debt_id' => $debt->id,
                    'amount' => $debt->amount,
                    'overdue_hours' => $debt->overdue_hours,
                    'payment_status' => $debt->payment_status,
                    'exeat_request_id' => $debt->exeat_request_id,
                    'departure_date' => $debt->exeatRequest->departure_date ?? null,
                    'return_date' => $debt->exeatRequest->return_date ?? null,
                ];
            });

            return response()->json([
                'status' => 'error',
                'message' => 'You have outstanding exeat debts that must be cleared before creating a new exeat request.',
                'details' => [
                    'total_debt_amount' => $totalDebt,
                    'number_of_debts' => $unpaidDebts->count(),
                    'debts' => $debtDetails,
                    'payment_instructions' => 'Please pay your outstanding debts through the payment system or contact the admin office for assistance.'
                ]
            ], 403);
        }

        // Get student academic info for matric_no
        $studentAcademic = StudentAcademic::where('student_id', $user->id)->first();
        // Get parent/guardian contact info
        $studentContact = StudentContact::where('student_id', $user->id)->first();
        // Get current accommodation info based on active session
        $accommodationHistory = VunaAccomodationHistory::getCurrentAccommodationForStudent($user->id);
        $accommodation = null;
        if ($accommodationHistory && $accommodationHistory->accommodation) {
            $accommodation = $accommodationHistory->accommodation->name;
        }
        // Prevent new request if previous is not completed
        $existing = ExeatRequest::where('student_id', $user->id)
            ->whereNotIn('status', ['completed', 'rejected']) // Optional: allow new request after rejection
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'You already have an active exeat request. Please wait until it is completed or rejected before submitting a new one.'
            ], 403);
        }
        // Get category
        $category = ExeatCategory::find($validated['category_id']);
        $isMedical = strtolower($category->name) === 'medical';
        $initialStatus = $isMedical ? 'cmd_review' : 'secretary_review';
        $exeat = ExeatRequest::create([
            'student_id' => $user->id,
            'matric_no' => $studentAcademic ? $studentAcademic->matric_no : null,
            'category_id' => $validated['category_id'],
            'reason' => $validated['reason'],
            'destination' => $validated['destination'],
            'departure_date' => $validated['departure_date'],
            'return_date' => $validated['return_date'],
            'preferred_mode_of_contact' => $validated['preferred_mode_of_contact'],
            'parent_surname' => $studentContact ? $studentContact->surname : null,
            'parent_othernames' => $studentContact ? $studentContact->other_names : null,
            'parent_phone_no' => $studentContact ? $studentContact->phone_no : null,
            'parent_phone_no_two' => $studentContact ? $studentContact->phone_no_two : null,
            'parent_email' => $studentContact ? $studentContact->email : null,
            'student_accommodation' => $accommodation,
            'status' => $initialStatus,
            'is_medical' => $isMedical,
        ]);
        // Create first approval stage
        \App\Models\ExeatApproval::create([
            'exeat_request_id' => $exeat->id,
            'role' => $isMedical ? 'cmd' : 'secretary',
            'status' => 'pending',
        ]);

        // Weekdays notification will be sent after dean approval instead of at creation

        // Send confirmation notification to student
        try {
            $this->notificationService->sendSubmissionConfirmation($exeat);
        } catch (\Exception $e) {
            Log::error('Failed to send submission confirmation', ['error' => $e->getMessage(), 'exeat_id' => $exeat->id]);
        }

        // Send approval required notification to appropriate staff
        try {
            $role = $isMedical ? 'cmd' : 'secretary';
            $this->notificationService->sendApprovalRequiredNotification($exeat, $role);
        } catch (\Exception $e) {
            Log::error('Failed to send approval required notification', ['error' => $e->getMessage(), 'exeat_id' => $exeat->id]);
        }

        Log::info('Student created exeat request', ['student_id' => $user->id, 'exeat_id' => $exeat->id]);
        return response()->json(['message' => 'Exeat request created successfully.', 'exeat_request' => $exeat], 201);
    }


    // GET /api/student/profile
public function profile(Request $request)
{
    $user = $request->user();

    $studentAcademic = StudentAcademic::where('student_id', $user->id)->first();
    $studentContact = StudentContact::where('student_id', $user->id)->first();
    $accommodationHistory = VunaAccomodationHistory::getCurrentAccommodationForStudent($user->id);

    $accommodation = null;
    if ($accommodationHistory && $accommodationHistory->accommodation) {
        $accommodation = $accommodationHistory->accommodation->name;
    }

    return response()->json([
        'profile' => [
            'matric_no' => $studentAcademic?->matric_no,
            'parent_surname' => $studentContact?->surname,
            'parent_othernames' => $studentContact?->other_names,
            'parent_phone_no' => $studentContact?->phone_no,
            'parent_phone_no_two' => $studentContact?->phone_no_two,
            'parent_email' => $studentContact?->email,
            'student_accommodation' => $accommodation,
        ]
    ]);
}

public function categories()
{
    return response()->json([
        'categories' => ExeatCategory::all(['id', 'name', 'description'])
    ]);
}

    // GET /api/student/exeat-requests
    public function index(Request $request)
    {
        $user = $request->user();
        $exeats = ExeatRequest::where('student_id', $user->id)
            ->with(['category:id,name', 'student:id,fname,lname,passport'])
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json(['exeat_requests' => $exeats]);
    }

    // GET /api/student/exeat-requests/{id}
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $exeat = ExeatRequest::where('id', $id)
            ->where('student_id', $user->id)
            ->with(['category:id,name', 'student:id,fname,lname,passport,phone'])
            ->first();
        if (!$exeat) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }
        return response()->json(['exeat_request' => $exeat]);
    }

    // POST /api/student/exeat-requests/{id}/appeal
    public function appeal(Request $request, $id)
    {
        $user = $request->user();
        $exeat = ExeatRequest::where('id', $id)->where('student_id', $user->id)->first();
        if (!$exeat) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }
        $validated = $request->validate([
            'appeal_reason' => 'required|string',
        ]);
        $exeat->appeal_reason = $validated['appeal_reason'];
        $exeat->status = 'appeal';
        $exeat->save();
        Log::info('Student appealed exeat request', ['student_id' => $user->id, 'exeat_id' => $exeat->id]);
        return response()->json(['message' => 'Appeal submitted successfully.', 'exeat_request' => $exeat]);
    }

    // GET /api/student/exeat-requests/{id}/download
    public function download(Request $request, $id)
    {
        $user = $request->user();
        $exeat = ExeatRequest::where('id', $id)->where('student_id', $user->id)->first();
        if (!$exeat) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }
        if ($exeat->status !== 'approved') {
            return response()->json(['message' => 'Exeat request is not approved yet.'], 403);
        }
        // For demo: return a JSON with a fake QR code string (in real app, generate PDF/QR)
        $qrCode = 'QR-' . $exeat->id . '-' . $exeat->student_id;
        return response()->json([
            'exeat_request' => $exeat,
            'qr_code' => $qrCode,
            'download_url' => null // Implement PDF/QR download as needed
        ]);
    }

    /**
     * Get the history of an exeat request
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function history(Request $request, $id)
    {
        $user = $request->user();
        $exeat = ExeatRequest::where('id', $id)->where('student_id', $user->id)->first();
        if (!$exeat) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }

        // Add pagination with configurable per_page parameter
        $perPage = $request->get('per_page', 20); // Default 20 items per page
        $perPage = min($perPage, 100); // Maximum 100 items per page
        
        // Get all audit logs related to this exeat request with pagination
        $auditLogs = AuditLog::where('target_type', 'exeat_request')
            ->where('target_id', $id)
            ->orderBy('timestamp', 'desc')
            ->with(['staff:id,fname,lname', 'student:id,fname,lname,passport'])
            ->paginate($perPage);

        // Get all approvals with their staff information (usually small dataset, no pagination needed)
        $approvals = ExeatApproval::where('exeat_request_id', $id)
            ->with('staff:id,fname,lname')
            ->orderBy('updated_at', 'desc')
            ->get();

        // Combine the data for a complete history
        $history = [
            'audit_logs' => $auditLogs->items(),
            'audit_logs_pagination' => [
                'current_page' => $auditLogs->currentPage(),
                'last_page' => $auditLogs->lastPage(),
                'per_page' => $auditLogs->perPage(),
                'total' => $auditLogs->total(),
                'from' => $auditLogs->firstItem(),
                'to' => $auditLogs->lastItem(),
                'has_more_pages' => $auditLogs->hasMorePages()
            ],
            'approvals' => $approvals,
            'exeat_request' => $exeat
        ];

        Log::info('Student viewed exeat request history', ['student_id' => $user->id, 'exeat_id' => $id]);

        return response()->json(['history' => $history]);
    }
}
