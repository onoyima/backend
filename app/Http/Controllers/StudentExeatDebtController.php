<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\StudentExeatDebt;
use App\Models\Staff;
use App\Models\Student;
use App\Services\ExeatNotificationService;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class StudentExeatDebtController extends Controller
{
    /**
     * The exeat notification service.
     *
     * @var \App\Services\ExeatNotificationService
     */
    protected $notificationService;

    /**
     * The Paystack service.
     *
     * @var \App\Services\PaystackService
     */
    protected $paystackService;

    /**
     * Create a new controller instance.
     *
     * @param \App\Services\ExeatNotificationService $notificationService
     * @param \App\Services\PaystackService $paystackService
     * @return void
     */
    public function __construct(ExeatNotificationService $notificationService, PaystackService $paystackService)
    {
        $this->notificationService = $notificationService;
        $this->paystackService = $paystackService;
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
            'payment_method' => 'required|string|in:paystack',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $debt = StudentExeatDebt::findOrFail($id);

        // Only allow updating if the debt is unpaid
        if ($debt->payment_status !== 'unpaid') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot update payment proof for a debt that is already paid or cleared'
            ], 422);
        }

        // Get the authenticated student
        $student = Student::find(Auth::id());

        if (!$student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Student not found'
            ], 404);
        }

        // Check if the student owns this debt
        if ($debt->student_id !== $student->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. You can only pay your own debts.'
            ], 403);
        }

        // Initialize Paystack transaction
        $result = $this->paystackService->initializeTransaction($debt, $student);
        
        if (!$result['success']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message']
            ], 422);
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Payment initialized successfully',
            'data' => [
                'authorization_url' => $result['data']['authorization_url'],
                'access_code' => $result['data']['access_code'],
                'reference' => $result['data']['reference']
            ]
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

        $debt = StudentExeatDebt::with('exeatRequest')->findOrFail($id);

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
            'target_type' => 'student_exeat_debt',
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

    /**
     * Verify a Paystack payment transaction.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function verifyPayment(Request $request, $id)
    {
        $debt = StudentExeatDebt::findOrFail($id);
        
        // Get the authenticated student
        $student = Student::find(Auth::id());

        if (!$student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Student not found'
            ], 404);
        }

        // Check if the student owns this debt
        if ($debt->student_id !== $student->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. You can only verify your own debt payments.'
            ], 403);
        }

        // Get the reference from the request or use the one stored in the debt
        $reference = $request->reference ?? $debt->payment_reference;

        if (!$reference) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment reference not found'
            ], 422);
        }

        // Verify the transaction with Paystack
        $result = $this->paystackService->verifyTransaction($reference);

        if (!$result['success']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message']
            ], 422);
        }

        // Transaction was successful, update the debt record
        DB::beginTransaction();
        
        try {
            $debt->payment_status = 'cleared'; // Mark as cleared immediately
            $debt->payment_date = now();
            $debt->cleared_at = now();
            $debt->payment_reference = $reference;
            $debt->save();

            // Create audit log
            AuditLog::create([
                'staff_id' => null,
                'student_id' => $debt->student_id,
                'action' => 'payment_verified_and_cleared',
                'target_type' => 'student_exeat_debt',
                'target_id' => $debt->id,
                'details' => json_encode([
                    'payment_reference' => $reference,
                    'payment_date' => $debt->payment_date,
                    'amount' => $debt->amount,
                    'payment_method' => 'paystack',
                    'transaction_data' => $result['data']
                ]),
                'timestamp' => now(),
            ]);
            
            // Send notification to student when debt is cleared
            try {
                if ($debt->exeatRequest) {
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

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Payment verified and debt cleared successfully.',
                'data' => $debt
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update debt after payment verification', [
                'debt_id' => $debt->id,
                'student_id' => $debt->student_id,
                'reference' => $reference,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while processing your payment verification. Please contact support.'
            ], 500);
        }
    }
}
