<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExeatRequest;
use App\Models\AuditLog;
use App\Services\ExeatWorkflowService;
use App\Services\ExeatNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use App\Http\Requests\BulkExeatOperationRequest;
use App\Http\Requests\SpecialDeanOverrideRequest;
use App\Http\Requests\FilterExeatRequestsRequest;

class AdminBulkOperationsController extends Controller
{
    protected $workflowService;
    protected $notificationService;

    public function __construct(
        ExeatWorkflowService $workflowService,
        ExeatNotificationService $notificationService
    ) {
        $this->workflowService = $workflowService;
        $this->notificationService = $notificationService;
    }

    /**
     * Get filtered exeat requests for bulk operations
     */
    public function getFilteredRequests(FilterExeatRequestsRequest $request): JsonResponse
    {
        $user = $request->user();

        // Check if user is admin or dean
        if (!$this->isAdminOrDean($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = ExeatRequest::with(['student:id,fname,lname,matric_number', 'approvals.staff:id,fname,lname']);

        // Apply filters
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('student_search')) {
            $query->whereHas('student', function ($q) use ($request) {
                $q->where('fname', 'like', '%' . $request->student_search . '%')
                  ->orWhere('lname', 'like', '%' . $request->student_search . '%')
                  ->orWhere('matric_number', 'like', '%' . $request->student_search . '%');
            });
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $exeats = $query->orderBy('created_at', 'desc')
                       ->paginate($request->get('per_page', 20));

        return response()->json([
            'message' => 'Filtered exeat requests retrieved successfully',
            'data' => $exeats
        ]);
    }

    /**
     * Bulk approve selected exeat requests
     */
    public function bulkApprove(BulkExeatOperationRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->isAdminOrDean($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'exeat_ids' => 'required|array|min:1',
            'exeat_ids.*' => 'integer|exists:exeat_requests,id',
            'comment' => 'nullable|string|max:1000',
            'notify_students' => 'boolean'
        ]);

        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($validated['exeat_ids'] as $exeatId) {
                try {
                    $exeat = ExeatRequest::with('student')->find($exeatId);

                    if (!$exeat) {
                        $errors[] = "Exeat request {$exeatId} not found";
                        $failedCount++;
                        continue;
                    }

                    // Check if exeat can be approved
                    if (in_array($exeat->status, ['approved', 'completed', 'cancelled', 'revoked'])) {
                        $errors[] = "Exeat request {$exeatId} cannot be approved (current status: {$exeat->status})";
                        $failedCount++;
                        continue;
                    }

                    // Update status based on current workflow stage
                    $newStatus = $this->getNextApprovalStatus($exeat->status, $exeat->is_medical);
                    $exeat->update(['status' => $newStatus]);

                    // Create audit log
                    AuditLog::create([
                        'target_type' => 'exeat_request',
                        'target_id' => $exeat->id,
                        'staff_id' => $user->id,
                        'student_id' => $exeat->student_id,
                        'action' => 'bulk_approval',
                        'details' => "Bulk approved by {$user->fname} {$user->lname}. Comment: " . ($validated['comment'] ?? 'No comment'),
                        'timestamp' => now()
                    ]);

                    // Send notification if requested
                    if ($validated['notify_students'] ?? true) {
                        $this->notificationService->sendApprovalNotification($exeat, $validated['comment'] ?? null);
                    }

                    $successCount++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to approve exeat request {$exeatId}: " . $e->getMessage();
                    $failedCount++;
                }
            }

            DB::commit();

            Log::info('Bulk approval completed', [
                'user_id' => $user->id,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'exeat_ids' => $validated['exeat_ids']
            ]);

            return response()->json([
                'message' => "Bulk approval completed. {$successCount} approved, {$failedCount} failed.",
                'data' => [
                    'success_count' => $successCount,
                    'failed_count' => $failedCount,
                    'errors' => $errors
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk approval failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'exeat_ids' => $validated['exeat_ids']
            ]);

            return response()->json([
                'message' => 'Bulk approval failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk reject selected exeat requests
     */
    public function bulkReject(BulkExeatOperationRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->isAdminOrDean($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'exeat_ids' => 'required|array|min:1',
            'exeat_ids.*' => 'integer|exists:exeat_requests,id',
            'reason' => 'required|string|max:1000',
            'notify_students' => 'boolean'
        ]);

        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($validated['exeat_ids'] as $exeatId) {
                try {
                    $exeat = ExeatRequest::with('student')->find($exeatId);

                    if (!$exeat) {
                        $errors[] = "Exeat request {$exeatId} not found";
                        $failedCount++;
                        continue;
                    }

                    // Check if exeat can be rejected
                    if (in_array($exeat->status, ['rejected', 'completed', 'cancelled', 'revoked'])) {
                        $errors[] = "Exeat request {$exeatId} cannot be rejected (current status: {$exeat->status})";
                        $failedCount++;
                        continue;
                    }

                    // Update status to rejected
                    $exeat->update(['status' => 'rejected']);

                    // Create audit log
                    AuditLog::create([
                        'target_type' => 'exeat_request',
                        'target_id' => $exeat->id,
                        'staff_id' => $user->id,
                        'student_id' => $exeat->student_id,
                        'action' => 'bulk_rejection',
                        'details' => "Bulk rejected by {$user->fname} {$user->lname}. Reason: {$validated['reason']}",
                        'timestamp' => now()
                    ]);

                    // Send notification if requested
                    if ($validated['notify_students'] ?? true) {
                        $this->notificationService->sendRejectionNotification($exeat, $validated['reason']);
                    }

                    $successCount++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to reject exeat request {$exeatId}: " . $e->getMessage();
                    $failedCount++;
                }
            }

            DB::commit();

            Log::info('Bulk rejection completed', [
                'user_id' => $user->id,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'exeat_ids' => $validated['exeat_ids']
            ]);

            return response()->json([
                'message' => "Bulk rejection completed. {$successCount} rejected, {$failedCount} failed.",
                'data' => [
                    'success_count' => $successCount,
                    'failed_count' => $failedCount,
                    'errors' => $errors
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk rejection failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'exeat_ids' => $validated['exeat_ids']
            ]);

            return response()->json([
                'message' => 'Bulk rejection failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Special Dean Override - Bypass entire workflow to security/hostel signout
     */
    public function specialDeanOverride(SpecialDeanOverrideRequest $request): JsonResponse
    {
        $user = $request->user();

        // Only deans can use special override
        if (!$this->isDean($user)) {
            return response()->json(['message' => 'Only deans can use special override'], 403);
        }

        $validated = $request->validate([
            'exeat_ids' => 'required|array|min:1',
            'exeat_ids.*' => 'integer|exists:exeat_requests,id',
            'override_reason' => 'required|string|max:1000',
            'notify_students' => 'boolean',
            'emergency_override' => 'boolean'
        ]);

        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($validated['exeat_ids'] as $exeatId) {
                try {
                    $exeat = ExeatRequest::with('student')->find($exeatId);

                    if (!$exeat) {
                        $errors[] = "Exeat request {$exeatId} not found";
                        $failedCount++;
                        continue;
                    }

                    // Check if exeat can be overridden
                    if (in_array($exeat->status, ['completed', 'cancelled', 'revoked'])) {
                        $errors[] = "Exeat request {$exeatId} cannot be overridden (current status: {$exeat->status})";
                        $failedCount++;
                        continue;
                    }

                    $previousStatus = $exeat->status;

                    // Special override - skip directly to security signout stage
                    $exeat->update([
                        'status' => 'security_signout',
                        'dean_override' => true,
                        'dean_override_reason' => $validated['override_reason'],
                        'dean_override_by' => $user->id,
                        'dean_override_at' => now(),
                        'emergency_override' => $validated['emergency_override'] ?? false
                    ]);

                    // Create audit log for override
                    AuditLog::create([
                        'target_type' => 'exeat_request',
                        'target_id' => $exeat->id,
                        'staff_id' => $user->id,
                        'student_id' => $exeat->student_id,
                        'action' => 'dean_special_override',
                        'details' => "Dean special override from '{$previousStatus}' to 'security_signout'. Reason: {$validated['override_reason']}",
                        'timestamp' => now()
                    ]);

                    // Send notification if requested
                    if ($validated['notify_students'] ?? true) {
                        $message = "Your exeat request has been specially approved by the Dean and is now ready for security signout. Reason: {$validated['override_reason']}";
                        $this->notificationService->sendApprovalNotification($exeat, $message);
                    }

                    $successCount++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to override exeat request {$exeatId}: " . $e->getMessage();
                    $failedCount++;
                }
            }

            DB::commit();

            Log::warning('Dean special override completed', [
                'dean_id' => $user->id,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'exeat_ids' => $validated['exeat_ids'],
                'override_reason' => $validated['override_reason'],
                'emergency' => $validated['emergency_override'] ?? false
            ]);

            return response()->json([
                'message' => "Dean special override completed. {$successCount} overridden, {$failedCount} failed.",
                'data' => [
                    'success_count' => $successCount,
                    'failed_count' => $failedCount,
                    'errors' => $errors
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Dean special override failed', [
                'dean_id' => $user->id,
                'error' => $e->getMessage(),
                'exeat_ids' => $validated['exeat_ids']
            ]);

            return response()->json([
                'message' => 'Dean special override failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bulk operations statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->isAdminOrDean($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $stats = [
            'total_requests' => ExeatRequest::count(),
            'pending_requests' => ExeatRequest::whereIn('status', ['pending', 'cmd_review', 'secretary_review', 'parent_consent', 'dean_review'])->count(),
            'in_progress_requests' => ExeatRequest::whereIn('status', ['hostel_signout', 'security_signout', 'security_signin', 'hostel_signin'])->count(),
            'rejected_requests' => ExeatRequest::where('status', 'rejected')->count(),
            'completed_requests' => ExeatRequest::where('status', 'completed')->count(),
            'appeal_requests' => ExeatRequest::where('status', 'appeal')->count(),
            'status_breakdown' => [
                'pending' => ExeatRequest::where('status', 'pending')->count(),
                'cmd_review' => ExeatRequest::where('status', 'cmd_review')->count(),
                'secretary_review' => ExeatRequest::where('status', 'secretary_review')->count(),
                'parent_consent' => ExeatRequest::where('status', 'parent_consent')->count(),
                'dean_review' => ExeatRequest::where('status', 'dean_review')->count(),
                'hostel_signout' => ExeatRequest::where('status', 'hostel_signout')->count(),
                'security_signout' => ExeatRequest::where('status', 'security_signout')->count(),
                'security_signin' => ExeatRequest::where('status', 'security_signin')->count(),
                'hostel_signin' => ExeatRequest::where('status', 'hostel_signin')->count(),
                'completed' => ExeatRequest::where('status', 'completed')->count(),
                'rejected' => ExeatRequest::where('status', 'rejected')->count(),
                'appeal' => ExeatRequest::where('status', 'appeal')->count()
            ]
        ];

        return response()->json([
            'message' => 'Statistics retrieved successfully',
            'data' => $stats
        ]);
    }

    /**
     * Check if user is admin or dean
     */
    private function isAdminOrDean($user): bool
    {
        return $user && (
            $user->role === 'admin' ||
            $user->role === 'dean' ||
            $user->hasRole('admin') ||
            $user->hasRole('dean')
        );
    }

    /**
     * Check if user is dean
     */
    private function isDean($user): bool
    {
        return $user && (
            $user->role === 'dean' ||
            $user->hasRole('dean')
        );
    }

    /**
     * Get next approval status based on current status
     * This follows the actual ExeatWorkflowService advanceStage logic
     */
    private function getNextApprovalStatus(string $currentStatus, bool $isMedical = false): string
    {
        switch ($currentStatus) {
            case 'pending':
                return $isMedical ? 'cmd_review' : 'secretary_review';
            case 'cmd_review':
                return 'secretary_review';
            case 'secretary_review':
                return 'parent_consent';
            case 'parent_consent':
                return 'dean_review';
            case 'dean_review':
                return 'hostel_signout';
            case 'hostel_signout':
                return 'security_signout';
            case 'security_signout':
                return 'security_signin';
            case 'security_signin':
                return 'hostel_signin';
            case 'hostel_signin':
                return 'completed';
            default:
                return $currentStatus; // No change for unknown or final statuses
        }
    }
}
