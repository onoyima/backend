<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExeatApproval;
use App\Models\ExeatRequest;
use Illuminate\Support\Facades\Log;

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

    public function approve(Request $request, $id)
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
        $exeatRequest->status = 'approved';
        $exeatRequest->save();
        $approval->status = 'approved';
        $approval->comment = $validated['comment'] ?? null;
        $approval->save();
        Log::info('Staff approved exeat request', ['staff_id' => $user->id, 'exeat_id' => $id]);
        return response()->json(['message' => 'Exeat request approved.', 'exeat_request' => $exeatRequest]);
    }

    public function reject(Request $request, $id)
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
        $exeatRequest->status = 'rejected';
        $exeatRequest->save();
        $approval->status = 'rejected';
        $approval->comment = $validated['comment'] ?? null;
        $approval->save();
        Log::info('Staff rejected exeat request', ['staff_id' => $user->id, 'exeat_id' => $id]);
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
            'method' => 'required|string', // e.g., sms, email, whatsapp
            'message' => 'nullable|string',
        ]);
        // For demo: create a ParentConsent record (assume model exists)
        $parentConsent = \App\Models\ParentConsent::create([
            'exeat_request_id' => $exeatRequest->id,
            'student_contact_id' => null, // You may want to look up the parent contact
            'consent_status' => 'pending',
            'consent_method' => $validated['method'],
            'consent_token' => uniqid('consent_', true),
            'consent_timestamp' => null,
        ]);
        // In a real app, send notification (SMS, email, etc.) here
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
