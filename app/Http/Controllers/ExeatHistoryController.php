<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExeatRequest;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExeatHistoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get staff exeat history - exeats they have approved/rejected
     * GET /api/staff/exeat-history
     */
    public function getStaffExeatHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Check if user is staff
        $staff = Staff::where('email', $user->email)->first();
        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Staff only.'
            ], 403);
        }

        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'status' => 'string|in:pending,approved,rejected,cancelled,completed,dean_review,deput-dean_review,cmd_review',
            'date_from' => 'date',
            'date_to' => 'date|after_or_equal:date_from',
            'student_name' => 'string|max:255',
            'matric_no' => 'string|max:50',
            'sort_by' => 'string|in:created_at,departure_date,return_date,status',
            'sort_order' => 'string|in:asc,desc'
        ]);

        $perPage = $validated['per_page'] ?? 20;
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';

        // Get exeat requests that this staff member has been involved in approving
        $query = ExeatRequest::with([
            'student:id,fname,lname,email',
            'approvals' => function($q) use ($staff) {
                $q->where('staff_id', $staff->id);
            },
            'approvals.staff:id,fname,lname,email',
            'category:id,name'
        ])
        ->whereHas('approvals', function($q) use ($staff) {
            $q->where('staff_id', $staff->id);
        })
        ->orderBy($sortBy, $sortOrder);

        // Apply filters
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (isset($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        if (isset($validated['student_name'])) {
            $query->whereHas('student', function($q) use ($validated) {
                $q->where(DB::raw("CONCAT(fname, ' ', lname)"), 'like', '%' . $validated['student_name'] . '%');
            });
        }

        if (isset($validated['matric_no'])) {
            $query->where('matric_no', 'like', '%' . $validated['matric_no'] . '%');
        }

        $exeatHistory = $query->paginate($perPage);

        // Transform the data to include approval details
        $exeatHistory->getCollection()->transform(function ($exeat) {
            $approval = $exeat->approvals->first();
            
            return [
                'id' => $exeat->id,
                'student' => [
                    'id' => $exeat->student->id,
                    'name' => $exeat->student->fname . ' ' . $exeat->student->lname,
                    'matric_no' => $exeat->matric_no, // Get matric_no from exeat_requests table
                    'email' => $exeat->student->email
                ],
                'category' => $exeat->category ? $exeat->category->name : null,
                'reason' => $exeat->reason,
                'destination' => $exeat->destination,
                'departure_date' => $exeat->departure_date,
                'return_date' => $exeat->return_date,
                'status' => $exeat->status,
                'is_medical' => $exeat->is_medical,
                'approval_details' => $approval ? [
                    'approved_at' => $approval->created_at,
                    'approval_status' => $approval->status,
                    'comments' => $approval->comments
                ] : null,
                'created_at' => $exeat->created_at,
                'updated_at' => $exeat->updated_at
            ];
        });

        Log::info('Staff exeat history retrieved', [
            'staff_id' => $staff->id,
            'count' => $exeatHistory->total(),
            'filters' => $validated
        ]);

        return response()->json([
            'success' => true,
            'data' => $exeatHistory->items(),
            'pagination' => [
                'current_page' => $exeatHistory->currentPage(),
                'last_page' => $exeatHistory->lastPage(),
                'per_page' => $exeatHistory->perPage(),
                'total' => $exeatHistory->total(),
                'from' => $exeatHistory->firstItem(),
                'to' => $exeatHistory->lastItem()
            ],
            'summary' => [
                'total_processed' => $exeatHistory->total(),
                'approved_count' => ExeatRequest::whereHas('approvals', function($q) use ($staff) {
                    $q->where('staff_id', $staff->id)->where('status', 'approved');
                })->count(),
                'rejected_count' => ExeatRequest::whereHas('approvals', function($q) use ($staff) {
                    $q->where('staff_id', $staff->id)->where('status', 'rejected');
                })->count()
            ]
        ]);
    }

    /**
     * Get exeats by status
     * GET /api/exeats/by-status/{status}
     */
    public function getExeatsByStatus(Request $request, string $status): JsonResponse
    {
        $user = $request->user();
        
        // Validate status - include all workflow statuses
        $validStatuses = [
            'pending', 'cmd_review', 'deputy-dean_review', 'parent_consent', 
            'dean_review', 'hostel_signout', 'security_signout', 'security_signin', 
            'hostel_signin', 'completed', 'approved', 'rejected', 'cancelled'
        ];
        
        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Must be one of: ' . implode(', ', $validStatuses)
            ], 400);
        }

        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'date_from' => 'date',
            'date_to' => 'date|after_or_equal:date_from',
            'student_name' => 'string|max:255',
            'matric_no' => 'string|max:50',
            'is_medical' => 'boolean',
            'sort_by' => 'string|in:created_at,departure_date,return_date,updated_at',
            'sort_order' => 'string|in:asc,desc'
        ]);

        $perPage = $validated['per_page'] ?? 20;
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';

        // Check user role and apply appropriate filters
        $query = ExeatRequest::with([
            'student:id,fname,lname,email',
            'category:id,name',
            'approvals.staff:id,fname,lname'
        ])
        ->where('status', $status)
        ->orderBy($sortBy, $sortOrder);

        // If user is a student, only show their own exeats
        $student = Student::where('email', $user->email)->first();
        if ($student) {
            $query->where('student_id', $student->id);
        }
        // If user is staff, they can see all exeats (admin/dean level access)
        // Additional role-based filtering can be added here if needed

        // Apply filters
        if (isset($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (isset($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        if (isset($validated['student_name'])) {
            $query->whereHas('student', function($q) use ($validated) {
                $q->where(DB::raw("CONCAT(fname, ' ', lname)"), 'like', '%' . $validated['student_name'] . '%');
            });
        }

        if (isset($validated['matric_no'])) {
            $query->where('matric_no', 'like', '%' . $validated['matric_no'] . '%');
        }

        if (isset($validated['is_medical'])) {
            $query->where('is_medical', $validated['is_medical']);
        }

        $exeats = $query->paginate($perPage);

        // Transform the data
        $exeats->getCollection()->transform(function ($exeat) {
            return [
                'id' => $exeat->id,
                'student' => [
                    'id' => $exeat->student->id,
                    'name' => $exeat->student->fname . ' ' . $exeat->student->lname,
                    'matric_no' => $exeat->student->matric_no,
                    'email' => $exeat->student->email
                ],
                'category' => $exeat->category ? $exeat->category->name : null,
                'reason' => $exeat->reason,
                'destination' => $exeat->destination,
                'departure_date' => $exeat->departure_date,
                'return_date' => $exeat->return_date,
                'status' => $exeat->status,
                'is_medical' => $exeat->is_medical,
                'approvals' => $exeat->approvals->map(function($approval) {
                    return [
                        'staff_name' => $approval->staff->fname . ' ' . $approval->staff->lname,
                        'status' => $approval->status,
                        'comments' => $approval->comments,
                        'approved_at' => $approval->created_at
                    ];
                }),
                'created_at' => $exeat->created_at,
                'updated_at' => $exeat->updated_at
            ];
        });

        Log::info('Exeats retrieved by status', [
            'user_id' => $user->id,
            'status' => $status,
            'count' => $exeats->total(),
            'filters' => $validated
        ]);

        return response()->json([
            'success' => true,
            'data' => $exeats->items(),
            'pagination' => [
                'current_page' => $exeats->currentPage(),
                'last_page' => $exeats->lastPage(),
                'per_page' => $exeats->perPage(),
                'total' => $exeats->total(),
                'from' => $exeats->firstItem(),
                'to' => $exeats->lastItem()
            ],
            'status_summary' => [
                'status' => $status,
                'total_count' => $exeats->total(),
                'medical_count' => ExeatRequest::where('status', $status)->where('is_medical', true)->count(),
                'regular_count' => ExeatRequest::where('status', $status)->where('is_medical', false)->count()
            ]
        ]);
    }

    /**
     * Get exeat statistics by status
     * GET /api/exeats/statistics
     */
    public function getExeatStatistics(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'date_from' => 'date',
            'date_to' => 'date|after_or_equal:date_from',
            'group_by' => 'string|in:day,week,month'
        ]);

        $dateFrom = $validated['date_from'] ?? Carbon::now()->subMonth();
        $dateTo = $validated['date_to'] ?? Carbon::now();
        $groupBy = $validated['group_by'] ?? 'day';

        // Base query
        $query = ExeatRequest::whereBetween('created_at', [$dateFrom, $dateTo]);

        // If user is a student, only show their statistics
        $student = Student::where('email', $user->email)->first();
        if ($student) {
            $query->where('student_id', $student->id);
        }

        // Get status counts
        $statusCounts = $query->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Get medical vs regular counts
        $medicalCounts = $query->select('is_medical', DB::raw('count(*) as count'))
            ->groupBy('is_medical')
            ->pluck('count', 'is_medical')
            ->toArray();

        // Get time-based statistics
        $timeFormat = match($groupBy) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d'
        };

        $timeStats = $query->select(
            DB::raw("DATE_FORMAT(created_at, '$timeFormat') as period"),
            DB::raw('count(*) as count')
        )
        ->groupBy('period')
        ->orderBy('period')
        ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'status_breakdown' => [
                    'pending' => $statusCounts['pending'] ?? 0,
                    'approved' => $statusCounts['approved'] ?? 0,
                    'rejected' => $statusCounts['rejected'] ?? 0,
                    'cancelled' => $statusCounts['cancelled'] ?? 0,
                    'total' => array_sum($statusCounts)
                ],
                'type_breakdown' => [
                    'medical' => $medicalCounts[1] ?? 0,
                    'regular' => $medicalCounts[0] ?? 0
                ],
                'time_series' => $timeStats,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                    'group_by' => $groupBy
                ]
            ]
        ]);
    }
}