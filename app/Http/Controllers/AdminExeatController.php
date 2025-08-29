<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExeatRequest;
use App\Services\ExeatNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AdminExeatController extends Controller
{
    protected $notificationService;

    public function __construct(ExeatNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    // POST /api/admin/exeats/{id}/revoke
    public function revoke(Request $request, $id)
    {
        // Validate input
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
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

        // Check if exeat can be revoked
        if (in_array($exeat->status, ['revoked', 'rejected', 'cancelled'])) {
            return response()->json([
                'message' => 'This exeat request cannot be revoked as it is already ' . $exeat->status . '.'
            ], 409);
        }

        $admin = $request->user();
        $previousStatus = $exeat->status;

        DB::beginTransaction();
        try {
            // Update exeat status
            $exeat->status = 'revoked';
            $exeat->revoked_by = $admin->id;
            $exeat->revoked_at = now();
            $exeat->revocation_reason = $validated['reason'];
            $exeat->revocation_comment = $validated['comment'] ?? null;
            $exeat->save();

            // Send notification to student
            $this->notificationService->sendRejectionNotification(
                $exeat,
                'Your exeat request has been revoked by administration. Reason: ' . $validated['reason']
            );

            // Log the action
            Log::warning('Admin revoked exeat', [
                'exeat_id' => $id,
                'admin_id' => $admin->id,
                'previous_status' => $previousStatus,
                'reason' => $validated['reason'],
                'student_id' => $exeat->student_id
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Exeat request revoked successfully.',
                'exeat_request' => $exeat->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to revoke exeat request', [
                'exeat_id' => $id,
                'admin_id' => $admin->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to revoke exeat request. Please try again.',
                'error' => 'Internal server error'
            ], 500);
        }
    }
}
