<?php

namespace App\Http\Controllers;

use App\Models\StudentExeatDebt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminStudentDebtController extends Controller
{
    /**
     * Display a list of all student debts (Admin view).
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

        // Filter by specific student if provided
        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        // Filter by student name if provided
        if ($request->has('student_name')) {
            $query->whereHas('student', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->student_name . '%');
            });
        }

        $debts = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $debts
        ]);
    }

    /**
     * Display the specified student debt (Admin view).
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
     * Clear a student debt (Admin action).
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function clearDebt($id)
    {
        $debt = StudentExeatDebt::findOrFail($id);
        
        // Update debt status
        $debt->update([
            'payment_status' => 'cleared',
            'cleared_by_staff_id' => Auth::id(),
            'cleared_at' => now(),
            'clearance_notes' => 'Cleared by admin'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Student debt cleared successfully',
            'data' => $debt->load(['student', 'exeatRequest', 'clearedByStaff'])
        ]);
    }
}