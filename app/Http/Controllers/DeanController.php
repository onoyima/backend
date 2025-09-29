<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExeatRequest;
use App\Models\ExeatCategory;
use App\Models\ParentConsent;
use App\Models\AuditLog;
use App\Models\StudentExeatDebt;
use App\Services\ExeatWorkflowService;
use App\Services\ExeatNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class DeanController extends Controller
{
    protected $workflowService;
    protected $notificationService;

    /**
     * Create a new controller instance.
     *
     * @param  \App\Services\ExeatWorkflowService  $workflowService
     * @param  \App\Services\ExeatNotificationService  $notificationService
     * @return void
     */
    public function __construct(
        ExeatWorkflowService $workflowService,
        ExeatNotificationService $notificationService
    ) {
        $this->workflowService = $workflowService;
        $this->notificationService = $notificationService;
    }
    
    /**
     * Display a list of student debts for dean.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function studentDebts(Request $request)
    {
        $query = StudentExeatDebt::with(['student', 'exeatRequest', 'clearedByStaff']);

        // Filter by payment status if provided
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Filter by student if provided
        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        $debts = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $debts
        ]);
    }

    /**
     * Display the specified student debt for dean.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function showStudentDebt($id)
    {
        $debt = StudentExeatDebt::with(['student', 'exeatRequest', 'clearedByStaff'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $debt
        ]);
    }
    // GET /api/dean/exeat-requests
    public function index(Request $request)
    {
        // Add pagination with configurable per_page parameter
        $perPage = $request->get('per_page', 20); // Default 20 items per page
        $perPage = min($perPage, 100); // Maximum 100 items per page
        
        // For demo: return all approved/verified exeat requests
        $exeats = ExeatRequest::where('status', 'approved')
            ->with('student:id,fname,lname,passport,phone')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
            
        Log::info('Dean viewed all approved exeat requests', ['count' => $exeats->total()]);
        
        return response()->json([
            'exeat_requests' => $exeats->items(),
            'pagination' => [
                'current_page' => $exeats->currentPage(),
                'last_page' => $exeats->lastPage(),
                'per_page' => $exeats->perPage(),
                'total' => $exeats->total(),
                'from' => $exeats->firstItem(),
                'to' => $exeats->lastItem(),
                'has_more_pages' => $exeats->hasMorePages()
            ]
        ]);
    }

    // POST /api/dean/exeat-requests/bulk-approve
    public function bulkApprove(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);
        $count = ExeatRequest::whereIn('id', $validated['ids'])
            ->update(['status' => 'approved']);
        Log::info('Dean bulk approved exeat requests', ['ids' => $validated['ids']]);
        return response()->json(['message' => "$count exeat requests approved."]);
    }

    /**
     * Edit an exeat request (dean only)
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

        $dean = Auth::user();
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
                    'staff_id' => $dean->id,
                    'student_id' => $exeat->student_id,
                    'action' => 'exeat_edited_by_dean',
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
                    'Your exeat request has been modified by the Dean.'
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
                'dean_id' => $dean->id,
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
            Log::info('Student exeat debt recalculated by Dean', [
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

    /**
     * Recalculate exeat debt when return date is modified
     *
     * @param \App\Models\ExeatRequest $exeat
     * @param string $oldReturnDate
     * @param string $newReturnDate
     * @return void
     */
    protected function recalculateExeatDebt($exeat, $oldReturnDate, $newReturnDate)
    {
        // Find existing debt for this exeat request
        $debt = StudentExeatDebt::where('exeat_request_id', $exeat->id)->first();
        
        if (!$debt) {
            // No existing debt found, nothing to recalculate
            return;
        }
        
        // Calculate the difference in days between old and new return dates
        $oldDate = new \DateTime($oldReturnDate);
        $newDate = new \DateTime($newReturnDate);
        $daysDifference = $newDate->diff($oldDate)->days;
        
        // If new date is earlier than old date, no additional debt
        if ($newDate <= $oldDate) {
            return;
        }
        
        // Get the daily penalty rate from configuration or use default
        $dailyRate = config('exeat.debt.daily_rate', 1000); // Default to ₦1000 per day
        
        // Calculate additional debt amount
        $additionalAmount = $daysDifference * $dailyRate;
        
        // Update debt amount
        $oldAmount = $debt->amount;
        $debt->amount += $additionalAmount;
        $debt->save();
        
        // Create audit log for debt recalculation
        AuditLog::create([
            'staff_id' => auth()->id(),
            'student_id' => $exeat->student_id,
            'action' => 'debt_recalculated',
            'target_type' => 'student_exeat_debt',
            'target_id' => $debt->id,
            'details' => json_encode([
                'exeat_id' => $exeat->id,
                'old_return_date' => $oldReturnDate,
                'new_return_date' => $newReturnDate,
                'days_difference' => $daysDifference,
                'daily_rate' => $dailyRate,
                'old_amount' => $oldAmount,
                'additional_amount' => $additionalAmount,
                'new_amount' => $debt->amount
            ]),
            'timestamp' => now(),
        ]);
        
        // Send notification to student about debt recalculation
        try {
            $student = $exeat->student;
            if ($student) {
                $this->notificationService->sendDebtRecalculationNotification(
                    $student,
                    $exeat,
                    $additionalAmount,
                    $debt->amount
                );
            }
        } catch (\Exception $e) {
            // Log error but don't fail the request
            Log::error('Failed to send debt recalculation notification', [
                'exeat_id' => $exeat->id,
                'student_id' => $exeat->student_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear a student debt (dean only)
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearStudentDebt(Request $request, $id)
    {
        $validator = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        // Validate ID format
        if (!is_numeric($id) || $id <= 0) {
            return response()->json(['message' => 'Invalid student debt ID.'], 400);
        }

        $debt = StudentExeatDebt::with(['student', 'exeatRequest'])->find($id);
        if (!$debt) {
            return response()->json(['message' => 'Student debt not found.'], 404);
        }

        // Only allow clearing if the debt is marked as paid
        if ($debt->payment_status !== 'paid') {
            return response()->json([
                'message' => 'Cannot clear a debt that is not marked as paid'
            ], 422);
        }

        $dean = Auth::user();

        DB::beginTransaction();
        try {
            $debt->payment_status = 'cleared';
            $debt->cleared_by = $dean->id;
            $debt->cleared_at = now();
            $debt->notes = $request->notes;
            $debt->save();

            // Create audit log
            AuditLog::create([
                'staff_id' => $dean->id,
                'student_id' => $debt->student_id,
                'action' => 'debt_cleared_by_dean',
                'target_type' => 'student_exeat_debt',
                'target_id' => $debt->id,
                'details' => json_encode([
                    'cleared_by' => $dean->id,
                    'cleared_at' => $debt->cleared_at,
                    'amount' => $debt->amount,
                    'notes' => $debt->notes
                ]),
                'timestamp' => now(),
            ]);

            // Send notification to student
            $student = $debt->student;
            $this->notificationService->sendDebtClearanceNotification(
                $student,
                $debt->exeatRequest,
                "Your exeat debt of ₦{$debt->amount} has been cleared by the Dean."
            );

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Student debt has been cleared successfully.',
                'data' => $debt
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to clear student debt', [
                'debt_id' => $id,
                'dean_id' => $dean->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to clear student debt. Please try again.',
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
