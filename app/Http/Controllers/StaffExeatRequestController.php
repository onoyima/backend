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
    private function getAllowedStatuses(array $roleNames)
    {
        // Define all possible statuses in the exeat workflow
        $allStatuses = [
            'pending', 'cmd_review', 'deputy-dean_review', 'parent_consent', 
            'dean_review', 'hostel_signout', 'security_signout', 'security_signin', 
            'hostel_signin', 'completed', 'rejected', 'appeal'
        ];

        $roleStatusMap = [
            'cmd' => ['cmd_review'],
            'deputy_dean' => ['deputy-dean_review'],
            'dean' => $allStatuses, // Dean can see all statuses
            'dean2' => $allStatuses, // Dean2 can see all statuses
            'admin' => $allStatuses, // Admin can see all statuses
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
        ->exists();

    if ($alreadyApproved) {
        return response()->json(['message' => "You have already approved this request as '{$actingRole}'."], 409);
    }

    $validated = $request->validate(['comment' => 'nullable|string']);
    $workflow = new ExeatWorkflowService();

    DB::beginTransaction();
    try {
        $approval = ExeatApproval::create([
            'exeat_request_id' => $exeatRequest->id,
            'staff_id' => $user->id,
            'status' => 'approved',
            'comment' => $validated['comment'] ?? null,
            'role' => $actingRole,
        ]);

        $workflow->approve($exeatRequest, $approval, $approval->comment);

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
        ->exists();

    if ($alreadyActed) {
        return response()->json(['message' => "You have already taken action on this request as '{$actingRole}'."], 409);
    }

    $validated = $request->validate(['comment' => 'required|string']);
    $workflow = new ExeatWorkflowService();

    DB::beginTransaction();
    try {
        $approval = ExeatApproval::create([
            'exeat_request_id' => $exeatRequest->id,
            'staff_id' => $user->id,
            'status' => 'rejected',
            'comment' => $validated['comment'],
            'role' => $actingRole,
        ]);

        $workflow->reject($exeatRequest, $approval, $approval->comment);

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

            $workflow = new ExeatWorkflowService();
            $parentConsent = $workflow->sendParentConsent($exeatRequest, $validated['method'], $validated['message'] ?? null, $user->id);

            return response()->json([
                'message' => 'Parent consent request sent.',
                'parent_consent' => $parentConsent,
            ]);
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
}
