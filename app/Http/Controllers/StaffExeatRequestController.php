<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExeatApproval;
use App\Models\ExeatRequest;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;
use App\Services\ExeatWorkflowService;
use App\Http\Requests\StaffExeatApprovalRequest;
use Illuminate\Support\Facades\DB;

class StaffExeatRequestController extends Controller
{
    protected $workflowService;

    protected $notificationService;

    public function __construct(
        ExeatWorkflowService $workflowService,
        \App\Services\ExeatNotificationService $notificationService
    ) {
        $this->workflowService = $workflowService;
        $this->notificationService = $notificationService;
        $this->middleware('auth:sanctum');
    }

    private function getAllowedStatuses(array $roleNames)
    {
        // Define all workflow statuses (excluding completed, rejected, and appeal)
        $activeStatuses = [
            'pending', 'cmd_review', 'deputy-dean_review', 'parent_consent',
            'dean_review', 'hostel_signout', 'security_signout', 'security_signin',
            'hostel_signin', 'cancelled'
        ];

        $roleStatusMap = [
            'cmd' => ['cmd_review'],
            'deputy_dean' => ['deputy-dean_review', 'parent_consent'],
            'dean' => $activeStatuses, // Dean can see all active statuses
            'dean2' => $activeStatuses, // Dean2 can see all active statuses
            'admin' => $activeStatuses, // Admin can see all active statuses
            'hostel_admin' => ['hostel_signout', 'hostel_signin'],
            'security' => ['security_signout', 'security_signin'],
        ];

        $allowedStatuses = [];

        foreach ($roleNames as $role) {
            if (isset($roleStatusMap[$role])) {
                $allowedStatuses = array_merge($allowedStatuses, $roleStatusMap[$role]);
            } else {
                Log::notice('Role not mapped to statuses', ['role' => $role]);
            }
        }

        return array_unique($allowedStatuses);
    }

    private function getActingRole($user, $currentStatus)
    {
        $roleMap = [
            'cmd_review' => 'cmd',
            'deputy-dean_review' => 'deputy_dean',
            'dean_review' => 'dean',
            'hostel_signout' => 'hostel_admin',
            'hostel_signin' => 'hostel_admin',
            'security_signout' => 'security',
            'security_signin' => 'security',
        ];

        $roles = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();

        // If user has admin role, they can act as any role based on current status
        if (in_array('admin', $roles) && isset($roleMap[$currentStatus])) {
            return $roleMap[$currentStatus];
        }

        // For non-admin users, check if they have the specific role for the current status
        foreach ($roles as $role) {
            if (isset($roleMap[$currentStatus]) && $roleMap[$currentStatus] === $role) {
                return $role;
            }
        }

        return 'unknown';
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        $allowedStatuses = $this->getAllowedStatuses($roleNames);

        if (empty($allowedStatuses)) {
            return response()->json(['message' => 'No access to exeat requests.'], 403);
        }

        $query = ExeatRequest::query()->with('student:id,fname,lname,passport')->whereIn('status', $allowedStatuses);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $exeatRequests = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['exeat_requests' => $exeatRequests]);
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $approvalIds = ExeatApproval::where('staff_id', $user->id)->pluck('exeat_request_id');

        $totalRequests = ExeatRequest::whereIn('id', $approvalIds)->count();
        $pendingRequests = ExeatRequest::whereIn('id', $approvalIds)
            ->whereIn('status', ['pending', 'cmd_review', 'deputy-dean_review', 'parent_consent', 'dean_review'])
            ->count();
        $approvedRequests = ExeatRequest::whereIn('id', $approvalIds)
            ->where('status', 'completed')
            ->count();
        $rejectedRequests = ExeatRequest::whereIn('id', $approvalIds)
            ->where('status', 'rejected')
            ->count();

        $data = [
            'total_requests' => $totalRequests,
            'pending_requests' => $pendingRequests,
            'approved_requests' => $approvedRequests,
            'rejected_requests' => $rejectedRequests,
        ];

        $userRoles = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        if (in_array('admin', $userRoles) || in_array('dean', $userRoles)) {
            $byStatus = DB::table('exeat_requests')
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->toArray();

            $byDepartment = DB::table('exeat_requests')
                ->join('students', 'exeat_requests.student_id', '=', 'students.id')
                ->join('student_academics', 'students.id', '=', 'student_academics.student_id')
                ->join('departments', 'student_academics.department_id', '=', 'departments.id')
                ->select('departments.name as department', DB::raw('count(*) as count'))
                ->groupBy('departments.name')
                ->get()
                ->toArray();

            $byDate = DB::table('exeat_requests')
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->toArray();

            $data['analytics'] = [
                'by_status' => $byStatus,
                'by_department' => $byDepartment,
                'by_date' => $byDate
            ];
        }

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $exeatRequest = ExeatRequest::with('student:id,fname,lname,passport')->find($id);
        if (!$exeatRequest) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }

        $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        $allowedStatuses = $this->getAllowedStatuses($roleNames);

        if (!in_array($exeatRequest->status, $allowedStatuses)) {
            return response()->json(['message' => 'You do not have permission to view this request.'], 403);
        }

        return response()->json(['exeat_request' => $exeatRequest]);
    }
    
    /**
     * Edit an exeat request (staff only)
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(Request $request, $id)
    {
        // Validate input
        $validated = $request->validate([
            'category_id' => 'sometimes|required|exists:exeat_categories,id',
            'reason' => 'sometimes|required|string|max:500',
            'destination' => 'sometimes|required|string|max:255',
            'departure_date' => 'sometimes|required|date',
            'return_date' => 'sometimes|required|date|after_or_equal:departure_date',
            'actual_return_date' => 'sometimes|nullable|date',
            'status' => 'sometimes|required|string|in:pending,approved,rejected,completed',
            'is_medical' => 'sometimes|boolean',
            'comment' => 'nullable|string|max:1000'
        ]);

        // Validate ID format
        if (!is_numeric($id) || $id <= 0) {
            return response()->json(['message' => 'Invalid exeat request ID.'], 400);
        }

        $exeat = ExeatRequest::with(['student'])->find($id);
        if (!$exeat) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }

        $user = $request->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        $allowedStatuses = $this->getAllowedStatuses($roleNames);

        if (!in_array($exeat->status, $allowedStatuses)) {
            return response()->json(['message' => 'You do not have permission to edit this request.'], 403);
        }

        // Check if exeat can be edited
        if (in_array($exeat->status, ['revoked', 'rejected', 'cancelled']) && 
            (!isset($validated['status']) || $validated['status'] !== 'completed')) {
            return response()->json([
                'message' => 'This exeat request cannot be edited as it is already ' . $exeat->status . '.'
            ], 409);
        }

        $changes = [];
        $actualReturnDateChanged = false;
        $oldActualReturnDate = $exeat->actual_return_date;

        DB::beginTransaction();
        try {
            // Track changes for audit log
            foreach ($validated as $field => $value) {
                if (isset($exeat->$field) && $exeat->$field != $value) {
                    $changes[$field] = [
                        'from' => $exeat->$field,
                        'to' => $value
                    ];
                    
                    if ($field === 'actual_return_date') {
                        $actualReturnDateChanged = true;
                    }
                    
                    $exeat->$field = $value;
                }
            }

            // Only save if there are changes
            if (!empty($changes)) {
                $exeat->save();
                
                // Recalculate debt if actual_return_date was changed
                if ($actualReturnDateChanged) {
                    $this->recalculateDebt($exeat, $oldActualReturnDate);
                }

                // Create audit log entry
                AuditLog::create([
                    'staff_id' => $user->id,
                    'student_id' => $exeat->student_id,
                    'action' => 'exeat_edited_by_staff',
                    'target_type' => 'exeat_request',
                    'target_id' => $exeat->id,
                    'details' => json_encode([
                        'changes' => $changes,
                        'comment' => $validated['comment'] ?? null
                    ]),
                    'timestamp' => now(),
                ]);

                // Send notification to student
                $this->notificationService->sendExeatModifiedNotification(
                    $exeat,
                    'Your exeat request has been modified by staff.'
                );

                DB::commit();

                return response()->json([
                    'message' => 'Exeat request updated successfully.',
                    'exeat_request' => $exeat->fresh(),
                    'changes' => $changes
                ]);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'No changes were made to the exeat request.'
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to edit exeat request', [
                'exeat_id' => $id,
                'staff_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to edit exeat request. Please try again.',
                'error' => 'Internal server error'
            ], 500);
        }
    }
    
    /**
     * Recalculate student debt based on actual return date
     *
     * @param ExeatRequest $exeat
     * @param string|null $oldActualReturnDate
     * @return void
     */
    protected function recalculateDebt(ExeatRequest $exeat, $oldActualReturnDate)
    {
        // Skip if no actual return date is set
        if (!$exeat->actual_return_date) {
            return;
        }
        
        // Calculate days late
        $returnDate = \Carbon\Carbon::parse($exeat->return_date);
        $actualReturnDate = \Carbon\Carbon::parse($exeat->actual_return_date);
        $daysLate = max(0, $actualReturnDate->diffInDays($returnDate));
        
        // Only create/update debt if student returned late
        if ($actualReturnDate->gt($returnDate)) {
            // Check for existing debt
            $debt = StudentExeatDebt::where('exeat_request_id', $exeat->id)->first();
            
            // Get the fee amount from settings
            $feePerDay = config('exeat.late_return_fee_per_day', 1000); // Default to 1000 if not set
            $amount = $daysLate * $feePerDay;
            
            if ($debt) {
                // Update existing debt
                $debt->update([
                    'amount' => $amount,
                    'days_late' => $daysLate,
                    'description' => "Late return fee for {$daysLate} days"
                ]);
            } else {
                // Create new debt
                StudentExeatDebt::create([
                    'student_id' => $exeat->student_id,
                    'exeat_request_id' => $exeat->id,
                    'amount' => $amount,
                    'days_late' => $daysLate,
                    'payment_status' => 'unpaid',
                    'description' => "Late return fee for {$daysLate} days"
                ]);
            }
        } else if ($oldActualReturnDate) {
            // If the actual return date was changed and is now on time, remove any existing debt
            StudentExeatDebt::where('exeat_request_id', $exeat->id)->delete();
        }
    }

public function approve(StaffExeatApprovalRequest $request, $id)
{
    $user = $request->user();

    if (!($user instanceof \App\Models\Staff)) {
        return response()->json(['message' => 'Unauthorized.'], 403);
    }

    $exeatRequest = ExeatRequest::with('student:id,fname,lname,passport')->find($id);
    if (!$exeatRequest) {
        return response()->json(['message' => 'Exeat request not found.'], 404);
    }

    $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
    $allowedStatuses = $this->getAllowedStatuses($roleNames);

    if (!in_array($exeatRequest->status, $allowedStatuses)) {
        return response()->json(['message' => 'You do not have permission to approve this request at this stage.'], 403);
    }

    // ✅ Determine acting role
    $actingRole = $this->getActingRole($user, $exeatRequest->status);

    // ✅ Prevent duplicate approval for same role and request
    $alreadyApproved = ExeatApproval::where('exeat_request_id', $exeatRequest->id)
        ->where('staff_id', $user->id)
        ->where('role', $actingRole)
        ->where('method', $exeatRequest->status)
        ->exists();

    if ($alreadyApproved) {
        return response()->json(['message' => "You have already approved this request as '{$actingRole}'."], 409);
    }

    $validated = $request->validate(['comment' => 'nullable|string']);

    DB::beginTransaction();
    try {
        $approval = ExeatApproval::create([
            'exeat_request_id' => $exeatRequest->id,
            'staff_id' => $user->id,
            'status' => 'approved',
            'comment' => $validated['comment'] ?? null,
            'role' => $actingRole,
            'method' => $exeatRequest->status,
        ]);

        $this->workflowService->approve($exeatRequest, $approval, $approval->comment);

        DB::commit();
    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json(['message' => 'Approval failed: ' . $e->getMessage()], 500);
    }

    return response()->json(['message' => 'Exeat request approved.', 'exeat_request' => $exeatRequest]);
}




    public function reject(StaffExeatApprovalRequest $request, $id)
{
    $user = $request->user();

    if (!($user instanceof \App\Models\Staff)) {
        return response()->json(['message' => 'Unauthorized.'], 403);
    }

    $exeatRequest = ExeatRequest::with('student:id,fname,lname,passport')->find($id);
    if (!$exeatRequest) {
        return response()->json(['message' => 'Exeat request not found.'], 404);
    }

    $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
    $allowedStatuses = $this->getAllowedStatuses($roleNames);

    if (!in_array($exeatRequest->status, $allowedStatuses)) {
        return response()->json(['message' => 'You do not have permission to reject this request.'], 403);
    }

    // ✅ Determine acting role
    $actingRole = $this->getActingRole($user, $exeatRequest->status);

    // ✅ Prevent duplicate rejection for same role
    $alreadyActed = ExeatApproval::where('exeat_request_id', $exeatRequest->id)
        ->where('staff_id', $user->id)
        ->where('role', $actingRole)
        ->where('method', $exeatRequest->status)
        ->exists();

    if ($alreadyActed) {
        return response()->json(['message' => "You have already taken action on this request as '{$actingRole}'."], 409);
    }

    $validated = $request->validate(['comment' => 'required|string']);
    $workflow = $this->workflowService;

    DB::beginTransaction();
    try {
        $approval = ExeatApproval::create([
            'exeat_request_id' => $exeatRequest->id,
            'staff_id' => $user->id,
            'status' => 'rejected',
            'comment' => $validated['comment'],
            'role' => $actingRole,
            'method' => $exeatRequest->status,
        ]);

        $this->workflowService->reject($exeatRequest, $approval, $approval->comment);

        DB::commit();
    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json(['message' => 'Rejection failed: ' . $e->getMessage()], 500);
    }

    return response()->json(['message' => 'Exeat request rejected.', 'exeat_request' => $exeatRequest]);
}


  public function sendParentConsent(Request $request, $id)
        {
            $user = $request->user();
            if (!($user instanceof \App\Models\Staff)) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $exeatRequest = ExeatRequest::with('student:id,fname,lname,passport')->find($id);
            if (!$exeatRequest) {
                return response()->json(['message' => 'Exeat request not found.'], 404);
            }

            // Optional: prevent triggering it at the wrong time
            if ($exeatRequest->status !== 'parent_consent') {
                return response()->json(['message' => 'Parent consent can only be sent at the parent_consent stage.'], 403);
            }

            $validated = $request->validate([
                'method' => 'required|string',
                'message' => 'nullable|string',
            ]);

            $parentConsent = $this->workflowService->sendParentConsent($exeatRequest, $validated['method'], $validated['message'] ?? null, $user->id);

            // Check notification status and provide appropriate response
            $responseMessage = 'Parent consent request processed.';
            $responseCode = 200;
            
            if (isset($parentConsent->notification_status)) {
                switch ($parentConsent->notification_status) {
                    case 'success':
                        $responseMessage = 'Parent consent request sent successfully.';
                        break;
                    case 'no_email':
                        $responseMessage = 'Parent consent request created, but no parent email address is available for email notification.';
                        $responseCode = 422;
                        break;
                    case 'failed':
                        $responseMessage = 'Parent consent request created, but notification delivery failed.';
                        $responseCode = 422;
                        break;
                }
            }

            return response()->json([
                'message' => $responseMessage,
                'parent_consent' => $parentConsent,
                'notification_status' => $parentConsent->notification_status ?? 'unknown',
                'status_message' => $parentConsent->status_message ?? 'No status message available'
            ], $responseCode);
        }

    public function history(Request $request, $id)
    {
        $user = $request->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $exeatRequest = ExeatRequest::with('student:id,fname,lname,passport')->find($id);
        if (!$exeatRequest) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }

        $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        $allowedStatuses = $this->getAllowedStatuses($roleNames);

        if (!in_array($exeatRequest->status, $allowedStatuses)) {
            return response()->json(['message' => 'You do not have permission to view this history.'], 403);
        }

        $auditLogs = AuditLog::where('target_type', 'exeat_request')
            ->where('target_id', $id)
            ->orderBy('timestamp', 'desc')
            ->with(['staff:id,fname,lname', 'student:id,fname,lname,passport'])
            ->get();

        $approvals = ExeatApproval::where('exeat_request_id', $id)
            ->with('staff:id,fname,lname')
            ->orderBy('updated_at', 'desc')
            ->get();

        $history = [
            'audit_logs' => $auditLogs,
            'approvals' => $approvals,
            'exeat_request' => $exeatRequest
        ];

        return response()->json(['history' => $history]);
    }

    /**
     * Get role-based history - all exeat requests that have passed through the staff member's roles
     * Shows all requests that went through their role's review stage, regardless of who reviewed them
     */
    public function roleHistory(Request $request)
    {
        $user = $request->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();

        if (empty($roleNames)) {
            return response()->json(['message' => 'No roles assigned.'], 403);
        }

        // Define all possible statuses in the exeat workflow
        $allStatuses = [
            'pending', 'cmd_review', 'deputy-dean_review', 'parent_consent',
            'dean_review', 'hostel_signout', 'security_signout', 'security_signin',
            'hostel_signin', 'completed', 'rejected', 'appeal'
        ];

        // Map roles to their corresponding statuses that they handle
        $roleStatusMap = [
            'cmd' => ['cmd_review'],
            'deputy_dean' => ['deputy-dean_review'],
            'dean' => $allStatuses, // Dean can see all statuses
            'dean2' => $allStatuses, // Dean2 can see all statuses
            'admin' => $allStatuses, // Admin can see all statuses
            'hostel_admin' => ['hostel_signout', 'hostel_signin'],
            'security' => ['security_signout', 'security_signin'],
        ];

        // Get all statuses that this staff member's roles handle
        $handledStatuses = [];
        foreach ($roleNames as $role) {
            if (isset($roleStatusMap[$role])) {
                $handledStatuses = array_merge($handledStatuses, $roleStatusMap[$role]);
            }
        }

        $handledStatuses = array_unique($handledStatuses);

        if (empty($handledStatuses)) {
            return response()->json(['message' => 'No handled statuses found for your roles.'], 403);
        }

        // Check if user has admin, dean, or dean2 role - they can see ALL requests
        $canSeeAllRequests = array_intersect(['admin', 'dean', 'dean2'], $roleNames);

        if (!empty($canSeeAllRequests)) {
            // Admin, dean, and dean2 can see all exeat requests
            $allRequestIds = ExeatRequest::pluck('id')->toArray();
        } else {
            // Get all exeat requests that have passed through the workflow stages handled by this staff member's roles
            // This includes requests that have been at these statuses at any point, regardless of who processed them
            $requestsWithApprovals = ExeatApproval::whereIn('role', $roleNames)
                ->distinct()
                ->pluck('exeat_request_id')
                ->toArray();

            // Also get requests that are currently at or have passed through the statuses this staff can handle
            $requestsAtStatuses = ExeatRequest::whereIn('status', $handledStatuses)
                ->pluck('id')
                ->toArray();

            // Get requests that have audit logs showing they were at these statuses (passed through)
            $requestsFromAuditLogs = AuditLog::where('target_type', 'exeat_request')
                ->where(function($query) use ($handledStatuses) {
                    foreach ($handledStatuses as $status) {
                        $query->orWhere('details', 'like', "%to {$status}%");
                    }
                })
                ->distinct()
                ->pluck('target_id')
                ->toArray();

            // Combine all request IDs to get comprehensive role history
            $allRequestIds = array_unique(array_merge($requestsWithApprovals, $requestsAtStatuses, $requestsFromAuditLogs));
        }

        if (empty($allRequestIds)) {
            return response()->json([
                'message' => 'No exeat requests found for your roles.',
                'exeat_requests' => [],
                'roles' => $roleNames,
                'handled_statuses' => $handledStatuses
            ]);
        }

        // Get the exeat requests with their complete approval history
        $exeatRequests = ExeatRequest::whereIn('id', $allRequestIds)
            ->with([
                'student:id,fname,lname,passport',
                'approvals' => function($query) {
                    $query->with('staff:id,fname,lname')
                          ->orderBy('updated_at', 'desc');
                },
                'approvals.staff:id,fname,lname'
            ])
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        // Add role information to each request
        foreach ($exeatRequests as $request) {
            $request->staff_roles = $roleNames;
            $request->acting_roles = [];

            // Determine which roles this staff member could act as for this request
            foreach ($roleNames as $role) {
                if ($role === 'admin' || $role === 'dean') {
                    $request->acting_roles[] = $role;
                } elseif (isset($roleStatusMap[$role])) {
                    foreach ($roleStatusMap[$role] as $status) {
                        if ($request->status === $status ||
                            (isset($request->status_history) && strpos($request->status_history, $status) !== false)) {
                            $request->acting_roles[] = $role;
                            break;
                        }
                    }
                }
            }
        }

        return response()->json([
            'message' => 'Role-based exeat history retrieved successfully.',
            'exeat_requests' => $exeatRequests,
            'staff_roles' => $roleNames,
            'handled_statuses' => $handledStatuses
        ]);
    }

    /**
     * Get all pending parent consents that Deputy Dean can act upon.
     */
    public function getPendingParentConsents(Request $request)
    {
        $user = $request->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Check if staff has deputy_dean role
        $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        if (!in_array('deputy_dean', $roleNames)) {
            return response()->json([
                'message' => 'Unauthorized. Only Deputy Dean can access parent consents.'
            ], 403);
        }

        $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'student_id' => 'integer|exists:students,id',
            'date_from' => 'date',
            'date_to' => 'date|after_or_equal:date_from'
        ]);

        $perPage = $request->get('per_page', 20);

        $query = \App\Models\ParentConsent::with(['exeatRequest.student'])
            ->where('consent_status', 'pending')
            ->whereHas('exeatRequest', function ($q) {
                $q->where('status', 'parent_consent');
            })
            ->orderBy('created_at', 'asc');

        if ($request->has('student_id')) {
            $query->whereHas('exeatRequest', function ($q) use ($request) {
                $q->where('student_id', $request->student_id);
            });
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $consents = $query->paginate($perPage);

        return response()->json([
            'message' => 'Pending parent consents retrieved successfully.',
            'data' => $consents->items(),
            'pagination' => [
                'current_page' => $consents->currentPage(),
                'last_page' => $consents->lastPage(),
                'per_page' => $consents->perPage(),
                'total' => $consents->total(),
                'from' => $consents->firstItem(),
                'to' => $consents->lastItem()
            ]
        ]);
    }

    /**
     * Deputy Dean approves parent consent on behalf of parent.
     */
    public function approveParentConsent(Request $request, $consentId)
    {
        $user = $request->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Check if staff has deputy_dean role
        $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        if (!in_array('deputy_dean', $roleNames)) {
            return response()->json([
                'message' => 'Unauthorized. Only Deputy Dean can approve parent consents.'
            ], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
            'notify_parent' => 'boolean'
        ]);

        $consent = \App\Models\ParentConsent::with('exeatRequest.student')->find($consentId);

        if (!$consent) {
            return response()->json([
                'message' => 'Parent consent not found'
            ], 404);
        }

        if ($consent->consent_status !== 'pending') {
            return response()->json([
                'message' => 'This consent request has already been processed'
            ], 400);
        }

        if ($consent->exeatRequest->status !== 'parent_consent') {
            return response()->json([
                'message' => 'Exeat request is not in parent consent stage'
            ], 400);
        }

        // Check if consent has expired
        if ($consent->expires_at && now()->gt($consent->expires_at)) {
            return response()->json([
                'message' => 'This consent link has expired'
            ], 410);
        }

        $workflow = $this->workflowService;

        DB::beginTransaction();
        try {
            // Update consent status
            $consent->update([
                'consent_status' => 'approved',
                'acted_by_staff_id' => $user->id,
                'action_type' => 'deputy_dean_approval',
                'action_reason' => $request->reason,
                'acted_at' => now()
            ]);

            // Move exeat request to next stage
            $exeatRequest = $consent->exeatRequest;
            $exeatRequest->update(['status' => 'dean_review']);

            // Create audit log
            AuditLog::create([
                'target_type' => 'exeat_request',
                'target_id' => $exeatRequest->id,
                'staff_id' => $user->id,
                'student_id' => $exeatRequest->student_id,
                'action' => 'deputy_dean_parent_consent_approval',
                'details' => "Deputy Dean approved parent consent on behalf of parent. Reason: {$request->reason}",
                'timestamp' => now()
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Approval failed: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Parent consent approved successfully on behalf of parent',
            'data' => [
                'consent' => $consent->fresh(),
                'exeat_request' => $exeatRequest->fresh()
            ]
        ]);
    }

    /**
     * Deputy Dean rejects parent consent on behalf of parent.
     */
    /**
     * Send a comment to a student regarding their exeat request
     * This doesn't affect the exeat workflow status
     * Only one comment notification is allowed per exeat status
     */
    public function sendComment(Request $request, $id)
    {
        $user = $request->user();
        
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        
        $exeatRequest = ExeatRequest::with('student:id,fname,lname,passport')->find($id);
        if (!$exeatRequest) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }
        
        $validated = $request->validate([
            'comment' => 'required|string|max:500',
        ]);
        
        try {
            // Check if a comment notification has already been sent for the current status
            $currentStatus = $exeatRequest->status;
            $existingComment = \App\Models\ExeatNotification::where('exeat_request_id', $exeatRequest->id)
                ->where('notification_type', \App\Models\ExeatNotification::TYPE_STAFF_COMMENT)
                ->where('data->status', $currentStatus)
                ->first();
                
            if ($existingComment) {
                // Get the staff who sent the previous comment from audit log
                $previousCommentLog = AuditLog::where('target_type', 'exeat_request')
                    ->where('target_id', $exeatRequest->id)
                    ->where('action', 'staff_comment')
                    ->where('details', 'like', '%Staff sent comment to student:%')
                    ->with('staff:id,fname,lname')
                    ->orderBy('timestamp', 'desc')
                    ->first();
                
                $previousStaffName = 'Unknown Staff';
                $previousComment = 'Comment details not available';
                $sentAt = $existingComment->created_at->format('M j, Y g:i A');
                
                if ($previousCommentLog && $previousCommentLog->staff) {
                    $previousStaffName = "{$previousCommentLog->staff->fname} {$previousCommentLog->staff->lname}";
                    // Extract comment from audit log details
                    if (preg_match('/Staff sent comment to student: (.+)$/', $previousCommentLog->details, $matches)) {
                        $previousComment = $matches[1];
                    }
                }
                
                return response()->json([
                    'message' => 'A comment has already been sent for this exeat status. Please wait until the status changes to send another comment.',
                    'status' => $currentStatus,
                    'previous_comment' => [
                        'message' => $previousComment,
                        'sent_by' => $previousStaffName,
                        'sent_at' => $sentAt,
                        'notification_id' => $existingComment->id
                    ]
                ], 422);
            }
            
            // Send notification to student
            $notifications = $this->notificationService->sendStaffCommentNotification(
                $exeatRequest,
                $user,
                $validated['comment'],
                $currentStatus
            );
            
            // Create audit log
            AuditLog::create([
                'target_type' => 'exeat_request',
                'target_id' => $exeatRequest->id,
                'staff_id' => $user->id,
                'student_id' => $exeatRequest->student_id,
                'action' => 'staff_comment',
                'details' => "Staff sent comment to student: {$validated['comment']}",
                'timestamp' => now()
            ]);
            
            return response()->json([
                'message' => 'Comment sent to student successfully',
                'notifications' => $notifications
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send comment: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function rejectParentConsent(Request $request, $consentId)
    {
        $user = $request->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Check if staff has deputy_dean role
        $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        if (!in_array('deputy_dean', $roleNames)) {
            return response()->json([
                'message' => 'Unauthorized. Only Deputy Dean can reject parent consents.'
            ], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
            'notify_parent' => 'boolean'
        ]);

        $consent = \App\Models\ParentConsent::with('exeatRequest.student')->find($consentId);

        if (!$consent) {
            return response()->json([
                'message' => 'Parent consent not found'
            ], 404);
        }

        if ($consent->consent_status !== 'pending') {
            return response()->json([
                'message' => 'This consent request has already been processed'
            ], 400);
        }

        if ($consent->exeatRequest->status !== 'parent_consent') {
            return response()->json([
                'message' => 'Exeat request is not in parent consent stage'
            ], 400);
        }

        // Check if consent has expired
        if ($consent->expires_at && now()->gt($consent->expires_at)) {
            return response()->json([
                'message' => 'This consent link has expired'
            ], 410);
        }

        DB::beginTransaction();
        try {
            // Update consent status
            $consent->update([
                'consent_status' => 'rejected',
                'acted_by_staff_id' => $user->id,
                'action_type' => 'deputy_dean_rejection',
                'action_reason' => $request->reason,
                'acted_at' => now()
            ]);

            // Move exeat request to rejected status
            $exeatRequest = $consent->exeatRequest;
            $exeatRequest->update(['status' => 'rejected']);

            // Create audit log
            AuditLog::create([
                'target_type' => 'exeat_request',
                'target_id' => $exeatRequest->id,
                'staff_id' => $user->id,
                'student_id' => $exeatRequest->student_id,
                'action' => 'deputy_dean_parent_consent_rejection',
                'details' => "Deputy Dean rejected parent consent on behalf of parent. Reason: {$request->reason}",
                'timestamp' => now()
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Rejection failed: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Parent consent rejected successfully on behalf of parent',
            'data' => [
                'consent' => $consent->fresh(),
                'exeat_request' => $exeatRequest->fresh()
            ]
        ]);
    }

    /**
     * Get statistics for Deputy Dean parent consent actions.
     */
    public function getParentConsentStats()
    {
        $user = request()->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Check if staff has deputy_dean role
        $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        if (!in_array('deputy_dean', $roleNames)) {
            return response()->json([
                'message' => 'Unauthorized. Only Deputy Dean can access parent consent statistics.'
            ], 403);
        }

        $totalPending = \App\Models\ParentConsent::where('consent_status', 'pending')
            ->whereHas('exeatRequest', function ($q) {
                $q->where('status', 'parent_consent');
            })
            ->count();

        $totalActedByDeputyDean = \App\Models\ParentConsent::where('acted_by_staff_id', $user->id)
            ->whereIn('action_type', ['deputy_dean_approval', 'deputy_dean_rejection'])
            ->count();

        $approvedByDeputyDean = \App\Models\ParentConsent::where('acted_by_staff_id', $user->id)
            ->where('action_type', 'deputy_dean_approval')
            ->count();

        $rejectedByDeputyDean = \App\Models\ParentConsent::where('acted_by_staff_id', $user->id)
            ->where('action_type', 'deputy_dean_rejection')
            ->count();

        return response()->json([
            'message' => 'Parent consent statistics retrieved successfully.',
            'data' => [
                'pending_consents' => $totalPending,
                'total_acted_by_deputy_dean' => $totalActedByDeputyDean,
                'approved_by_deputy_dean' => $approvedByDeputyDean,
                'rejected_by_deputy_dean' => $rejectedByDeputyDean
            ]
        ]);
    }
}
