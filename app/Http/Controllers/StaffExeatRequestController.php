<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExeatApproval;
use App\Models\ExeatRequest;
use Illuminate\Support\Facades\Log;
use App\Services\ExeatWorkflowService;
use App\Http\Requests\StaffExeatApprovalRequest;

class StaffExeatRequestController extends Controller
{
    // GET /api/staff/exeat-requests
    public function index(Request $request)
    {
        $user = $request->user();
        // Only allow staff
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        // Get all exeat approvals assigned to this staff
        $approvalIds = ExeatApproval::where('staff_id', $user->id)->pluck('exeat_request_id');
        $exeatRequests = ExeatRequest::whereIn('id', $approvalIds)->orderBy('created_at', 'desc')->get();
        Log::info('Staff viewed assigned exeat requests', ['staff_id' => $user->id, 'count' => $exeatRequests->count()]);
        return response()->json(['exeat_requests' => $exeatRequests]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $approval = ExeatApproval::where('staff_id', $user->id)
            ->where('exeat_request_id', $id)
            ->first();
        if (!$approval) {
            return response()->json(['message' => 'Exeat request not assigned to you or not found.'], 404);
        }
        $exeatRequest = ExeatRequest::find($id);
        if (!$exeatRequest) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }
        Log::info('Staff viewed exeat request details', ['staff_id' => $user->id, 'exeat_id' => $id]);
        return response()->json(['exeat_request' => $exeatRequest]);
    }

   public function approve(StaffExeatApprovalRequest $request, $id)
{
    $user = $request->user();
    if (!($user instanceof \App\Models\Staff)) {
        return response()->json(['message' => 'Unauthorized.'], 403);
    }

    $approval = ExeatApproval::where('staff_id', $user->id)
        ->where('exeat_request_id', $id)
        ->first();

    if (!$approval) {
        return response()->json(['message' => 'Exeat request not assigned to you or not found.'], 404);
    }

    $exeatRequest = ExeatRequest::find($id);
    if (!$exeatRequest) {
        return response()->json(['message' => 'Exeat request not found.'], 404);
    }

    $validated = $request->validate([
        'comment' => 'nullable|string',
    ]);

    $workflow = new ExeatWorkflowService();

    try {
        $exeatRequest = $workflow->approve($exeatRequest, $approval, $validated['comment'] ?? null);
    } catch (\Exception $ex) {
        return response()->json(['message' => 'Approval failed: ' . $ex->getMessage()], 400);
    }

    return response()->json(['message' => 'Exeat request approved.', 'exeat_request' => $exeatRequest]);
}


    public function reject(StaffExeatApprovalRequest $request, $id)
    {
        $user = $request->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $approval = ExeatApproval::where('staff_id', $user->id)
            ->where('exeat_request_id', $id)
            ->first();
        if (!$approval) {
            return response()->json(['message' => 'Exeat request not assigned to you or not found.'], 404);
        }
        $exeatRequest = ExeatRequest::find($id);
        if (!$exeatRequest) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }
        $validated = $request->validate([
            'comment' => 'nullable|string',
        ]);
        $workflow = new ExeatWorkflowService();
        $exeatRequest = $workflow->reject($exeatRequest, $approval, $validated['comment'] ?? null);
        return response()->json(['message' => 'Exeat request rejected.', 'exeat_request' => $exeatRequest]);
    }

   public function sendParentConsent(Request $request, $id)
{
    $user = $request->user();
    if (!($user instanceof \App\Models\Staff)) {
        return response()->json(['message' => 'Unauthorized.'], 403);
    }

    $approval = ExeatApproval::where('staff_id', $user->id)
        ->where('exeat_request_id', $id)
        ->first();

    if (!$approval) {
        return response()->json(['message' => 'Exeat request not assigned to you or not found.'], 404);
    }

    $exeatRequest = ExeatRequest::find($id);
    if (!$exeatRequest) {
        return response()->json(['message' => 'Exeat request not found.'], 404);
    }

    $validated = $request->validate([
        'method' => 'required|string',
        'message' => 'nullable|string',
    ]);

    // 🔴 REMOVE this block:
    /*
    $parentConsent = \App\Models\ParentConsent::create([
        'exeat_request_id' => $exeatRequest->id,
        'student_contact_id' => null,
        'consent_status' => 'pending',
        'consent_method' => $validated['method'],
        'consent_token' => uniqid('consent_', true),
        'consent_timestamp' => null,
    ]);
    */

    // ✅ ADD THIS INSTEAD:
    $workflow = new ExeatWorkflowService();
    $parentConsent = $workflow->sendParentConsent($exeatRequest, $validated['method'], $validated['message'] ?? null);

    Log::info('Staff sent parent consent request', [
        'staff_id' => $user->id,
        'exeat_id' => $id,
        'method' => $validated['method'],
    ]);

    return response()->json([
        'message' => 'Parent consent request sent.',
        'parent_consent' => $parentConsent,
    ]);
}
}
