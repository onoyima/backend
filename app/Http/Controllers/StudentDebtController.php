<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\StudentExeatDebt;
use App\Models\Staff;
use App\Models\Student;
use App\Services\ExeatNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class StudentDebtController extends Controller
{
    /**
     * The exeat notification service.
     *
     * @var \App\Services\ExeatNotificationService
     */
    protected $notificationService;
    
    /**
     * Create a new controller instance.
     *
     * @param \App\Services\ExeatNotificationService $notificationService
     * @return void
     */
    public function __construct(ExeatNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    
    /**
     * Display a list of student debts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
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
     * Display the specified student debt.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $debt = StudentExeatDebt::with(['student', 'exeatRequest', 'clearedByStaff'])->findOrFail($id);
        
        return response()->json([
            'status' => 'success',
            'data' => $debt
        ]);
    }

    /**
     * Update the payment proof for a student debt.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updatePaymentProof(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_reference' => 'required|string|max:255',
            'payment_proof' => 'required|string',
            'payment_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $debt = StudentDebt::findOrFail($id);
        
        // Only allow updating if the debt is unpaid
        if ($debt->payment_status !== 'unpaid') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot update payment proof for a debt that is already paid or cleared'
            ], 422);
        }
        
        $debt->payment_reference = $request->payment_reference;
        $debt->payment_proof = $request->payment_proof;
        $debt->payment_date = $request->payment_date;
        $debt->payment_status = 'paid'; // Mark as paid but not yet cleared
        $debt->save();
        
        // Create audit log
        AuditLog::create([
            'staff_id' => null,
            'student_id' => $debt->student_id,
            'action' => 'payment_submitted',
            'target_type' => 'student_debt',
            'target_id' => $debt->id,
            'details' => json_encode([
                'payment_reference' => $debt->payment_reference,
                'payment_date' => $debt->payment_date,
                'amount' => $debt->amount
            ]),
            'timestamp' => now(),
        ]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Payment proof submitted successfully. Awaiting verification by admin or dean.',
            'data' => $debt
        ]);
    }

    /**
     * Clear a student debt (admin/dean only).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function clearDebt(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Get the authenticated staff member
        $staff = Staff::find(Auth::id());
        
        // Check if the staff is an admin or dean
        $isAuthorized = false;
        foreach ($staff->roles as $role) {
            if ($role->name === 'admin' || $role->name === 'dean') {
                $isAuthorized = true;
                break;
            }
        }
        
        if (!$isAuthorized) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only deans or admins can clear student debts.'
            ], 403);
        }

        $debt = StudentDebt::with('exeatRequest')->findOrFail($id);
        
        // Only allow clearing if the debt is marked as paid
        if ($debt->payment_status !== 'paid') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot clear a debt that is not marked as paid'
            ], 422);
        }
        
        $debt->payment_status = 'cleared';
        $debt->cleared_by = $staff->id;
        $debt->cleared_at = now();
        $debt->notes = $request->notes;
        $debt->save();
        
        // Create audit log
        AuditLog::create([
            'staff_id' => $staff->id,
            'student_id' => $debt->student_id,
            'action' => 'debt_cleared',
            'target_type' => 'student_debt',
            'target_id' => $debt->id,
            'details' => json_encode([
                'cleared_by' => $staff->id,
                'cleared_at' => $debt->cleared_at,
                'amount' => $debt->amount,
                'notes' => $debt->notes
            ]),
            'timestamp' => now(),
        ]);
        
        // Send notification to student when debt is cleared
        try {
            $student = Student::find($debt->student_id);
            if ($student && $debt->exeatRequest) {
                $this->notificationService->sendDebtClearanceNotification($student, $debt->exeatRequest);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the request
            Log::error('Failed to send debt clearance notification', [
                'debt_id' => $debt->id,
                'student_id' => $debt->student_id,
                'error' => $e->getMessage()
            ]);
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Student debt has been cleared successfully.',
            'data' => $debt
        ]);
    }
}