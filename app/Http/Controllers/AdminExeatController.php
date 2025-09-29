<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExeatRequest;
use App\Models\ExeatCategory;
use App\Models\AuditLog;
use App\Services\ExeatNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AdminExeatController extends Controller
{
    protected $notificationService;

    public function __construct(ExeatNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    
    /**
     * Edit an exeat request (admin only)
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

        $exeat = ExeatRequest::with(['student:id,fname,lname,passport,phone'])->find($id);
        if (!$exeat) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }

        // Check if exeat can be edited
        if (in_array($exeat->status, ['revoked', 'rejected', 'cancelled']) && 
            (!isset($validated['status']) || $validated['status'] !== 'completed')) {
            return response()->json([
                'message' => 'This exeat request cannot be edited as it is already ' . $exeat->status . '.'
            ], 409);
        }

        $admin = Auth::user();
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
                    'staff_id' => $admin->id,
                    'student_id' => $exeat->student_id,
                    'action' => 'exeat_edited',
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
                    'Your exeat request has been modified by administration.'
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
                'admin_id' => $admin->id,
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
        // Only recalculate if status is completed and there's an actual return date
        if ($exeat->status !== 'completed' || !$exeat->actual_return_date) {
            return;
        }
        
        $returnDate = \Carbon\Carbon::parse($exeat->return_date);
        $actualReturnDate = \Carbon\Carbon::parse($exeat->actual_return_date);
        
        // Calculate days late using exact 24-hour periods at 11:59 PM
        $daysLate = $this->calculateDaysOverdue($returnDate, $actualReturnDate);
        
        // Check if student returned late
        if ($daysLate > 0) {
            $debtAmount = $daysLate * 10000;
            
            // Find existing debt or create new one
            $debt = StudentExeatDebt::firstOrNew([
                'student_id' => $exeat->student_id,
                'exeat_request_id' => $exeat->id
            ]);
            
            $debt->amount = $debtAmount;
            $debt->payment_status = 'unpaid';
            $debt->save();
            
            // Log the debt calculation
            Log::info('Student exeat debt recalculated', [
                'exeat_id' => $exeat->id,
                'student_id' => $exeat->student_id,
                'days_late' => $daysLate,
                'amount' => $debtAmount
            ]);
        } else {
            // If student returned on time, remove any existing debt
            StudentExeatDebt::where('exeat_request_id', $exeat->id)->delete();
        }
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

        $exeat = ExeatRequest::with(['student:id,fname,lname,passport,phone'])->find($id);
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

    /**
     * Calculate days overdue using exact 24-hour periods at 11:59 PM
     * 
     * @param \Carbon\Carbon $returnDate
     * @param \Carbon\Carbon $actualReturnTime
     * @return int
     */
    private function calculateDaysOverdue(\Carbon\Carbon $returnDate, \Carbon\Carbon $actualReturnTime): int
    {
        // Set return date to 11:59 PM of the expected return date
        $returnDateEnd = $returnDate->copy()->setTime(23, 59, 59);
        
        // If actual return is before or at 11:59 PM of return date, no debt
        if ($actualReturnTime->lte($returnDateEnd)) {
            return 0;
        }
        
        // Calculate full 24-hour periods after 11:59 PM of return date
        $daysPassed = 0;
        $currentCheckDate = $returnDateEnd->copy();
        
        while ($currentCheckDate->lt($actualReturnTime)) {
            $currentCheckDate->addDay()->setTime(23, 59, 59);
            $daysPassed++;
        }
        
        return $daysPassed;
    }
}
