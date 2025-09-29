<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExeatRequest;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\Staff;
use App\Models\ExeatApproval;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // GET /api/reports/exeats
    public function exeats(Request $request)
    {
        // Add pagination with configurable per_page parameter
        $perPage = $request->get('per_page', 50); // Default 50 items per page for reports
        $perPage = min($perPage, 200); // Maximum 200 items per page for reports
        
        $exeats = ExeatRequest::orderBy('created_at', 'desc')->paginate($perPage);
        
        // For demo, return as JSON; in real app, stream CSV/Excel
        return response()->json([
            'exeats' => $exeats->items(),
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

    // GET /api/reports/audit-logs
    public function auditLogs(Request $request)
    {
        // Add pagination with configurable per_page parameter
        $perPage = $request->get('per_page', 50); // Default 50 items per page for reports
        $perPage = min($perPage, 200); // Maximum 200 items per page for reports
        
        $logs = AuditLog::orderBy('created_at', 'desc')->paginate($perPage);
        
        // For demo, return as JSON; in real app, stream CSV/Excel
        return response()->json([
            'audit_logs' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
                'has_more_pages' => $logs->hasMorePages()
            ]
        ]);
    }

    /**
     * GET /api/analytics/exeat-usage
     * Analytics for exeat usage patterns
     */
    public function exeatUsage(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'date',
            'date_to' => 'date|after_or_equal:date_from',
            'group_by' => 'string|in:day,week,month'
        ]);

        $dateFrom = $validated['date_from'] ?? Carbon::now()->subMonths(6);
        $dateTo = $validated['date_to'] ?? Carbon::now();
        $groupBy = $validated['group_by'] ?? 'month';

        // Total exeat requests
        $totalRequests = ExeatRequest::whereBetween('created_at', [$dateFrom, $dateTo])->count();

        // Status breakdown
        $statusBreakdown = ExeatRequest::whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Medical vs Regular breakdown
        $typeBreakdown = ExeatRequest::whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('is_medical', DB::raw('count(*) as count'))
            ->groupBy('is_medical')
            ->pluck('count', 'is_medical')
            ->toArray();

        // Time-based usage patterns
        $timeFormat = match($groupBy) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m'
        };

        $usagePattern = ExeatRequest::whereBetween('created_at', [$dateFrom, $dateTo])
            ->select(
                DB::raw("DATE_FORMAT(created_at, '$timeFormat') as period"),
                DB::raw('count(*) as total_requests'),
                DB::raw('sum(case when status = "approved" then 1 else 0 end) as approved_requests'),
                DB::raw('sum(case when is_medical = 1 then 1 else 0 end) as medical_requests')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Average processing time
        $avgProcessingTime = ExeatRequest::whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereIn('status', ['approved', 'rejected'])
            ->select(DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_hours'))
            ->value('avg_hours');

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_requests' => $totalRequests,
                    'avg_processing_time_hours' => round($avgProcessingTime ?? 0, 2)
                ],
                'status_breakdown' => $statusBreakdown,
                'type_breakdown' => [
                    'medical' => $typeBreakdown[1] ?? 0,
                    'regular' => $typeBreakdown[0] ?? 0
                ],
                'usage_pattern' => $usagePattern,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                    'group_by' => $groupBy
                ]
            ]
        ]);
    }

    /**
     * GET /api/analytics/student-trends
     * Analytics for student exeat trends
     */
    public function studentTrends(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'date',
            'date_to' => 'date|after_or_equal:date_from',
            'limit' => 'integer|min:1|max:50'
        ]);

        $dateFrom = $validated['date_from'] ?? Carbon::now()->subMonths(6);
        $dateTo = $validated['date_to'] ?? Carbon::now();
        $limit = $validated['limit'] ?? 20;

        // Most active students
        $mostActiveStudents = ExeatRequest::with('student:id,fname,lname,matric_no')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('student_id', DB::raw('count(*) as total_requests'))
            ->groupBy('student_id')
            ->orderBy('total_requests', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($item) {
                return [
                    'student_id' => $item->student_id,
                    'student_name' => $item->student ? $item->student->fname . ' ' . $item->student->lname : 'Unknown',
                    'matric_no' => $item->student ? $item->student->matric_no : 'Unknown',
                    'total_requests' => $item->total_requests
                ];
            });

        // Students with highest approval rates
        $highestApprovalRates = ExeatRequest::with('student:id,fname,lname,matric_no')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->select(
                'student_id',
                DB::raw('count(*) as total_requests'),
                DB::raw('sum(case when status = "approved" then 1 else 0 end) as approved_requests'),
                DB::raw('(sum(case when status = "approved" then 1 else 0 end) / count(*)) * 100 as approval_rate')
            )
            ->groupBy('student_id')
            ->having('total_requests', '>=', 3) // Only students with at least 3 requests
            ->orderBy('approval_rate', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($item) {
                return [
                    'student_id' => $item->student_id,
                    'student_name' => $item->student ? $item->student->fname . ' ' . $item->student->lname : 'Unknown',
                    'matric_no' => $item->student ? $item->student->matric_no : 'Unknown',
                    'total_requests' => $item->total_requests,
                    'approved_requests' => $item->approved_requests,
                    'approval_rate' => round($item->approval_rate, 2)
                ];
            });

        // Medical exeat trends
        $medicalTrends = ExeatRequest::with('student:id,fname,lname,matric_no')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('is_medical', true)
            ->select('student_id', DB::raw('count(*) as medical_requests'))
            ->groupBy('student_id')
            ->orderBy('medical_requests', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($item) {
                return [
                    'student_id' => $item->student_id,
                    'student_name' => $item->student ? $item->student->fname . ' ' . $item->student->lname : 'Unknown',
                    'matric_no' => $item->student ? $item->student->matric_no : 'Unknown',
                    'medical_requests' => $item->medical_requests
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'most_active_students' => $mostActiveStudents,
                'highest_approval_rates' => $highestApprovalRates,
                'medical_trends' => $medicalTrends,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]
        ]);
    }

    /**
     * GET /api/analytics/staff-performance
     * Analytics for staff approval performance
     */
    public function staffPerformance(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'date',
            'date_to' => 'date|after_or_equal:date_from',
            'limit' => 'integer|min:1|max:50'
        ]);

        $dateFrom = $validated['date_from'] ?? Carbon::now()->subMonths(6);
        $dateTo = $validated['date_to'] ?? Carbon::now();
        $limit = $validated['limit'] ?? 20;

        // Staff approval statistics
        $staffPerformance = ExeatApproval::with('staff:id,fname,lname,email,role')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->select(
                'staff_id',
                DB::raw('count(*) as total_approvals'),
                DB::raw('sum(case when status = "approved" then 1 else 0 end) as approved_count'),
                DB::raw('sum(case when status = "rejected" then 1 else 0 end) as rejected_count'),
                DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_response_time_hours')
            )
            ->groupBy('staff_id')
            ->orderBy('total_approvals', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($item) {
                return [
                    'staff_id' => $item->staff_id,
                    'staff_name' => $item->staff ? $item->staff->fname . ' ' . $item->staff->lname : 'Unknown',
                    'staff_email' => $item->staff ? $item->staff->email : 'Unknown',
                    'staff_role' => $item->staff ? $item->staff->role : 'Unknown',
                    'total_approvals' => $item->total_approvals,
                    'approved_count' => $item->approved_count,
                    'rejected_count' => $item->rejected_count,
                    'approval_rate' => $item->total_approvals > 0 ? round(($item->approved_count / $item->total_approvals) * 100, 2) : 0,
                    'avg_response_time_hours' => round($item->avg_response_time_hours ?? 0, 2)
                ];
            });

        // Fastest responding staff
        $fastestStaff = ExeatApproval::with('staff:id,fname,lname,email,role')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->select(
                'staff_id',
                DB::raw('count(*) as total_approvals'),
                DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_response_time_hours')
            )
            ->groupBy('staff_id')
            ->having('total_approvals', '>=', 5) // Only staff with at least 5 approvals
            ->orderBy('avg_response_time_hours', 'asc')
            ->limit($limit)
            ->get()
            ->map(function($item) {
                return [
                    'staff_id' => $item->staff_id,
                    'staff_name' => $item->staff ? $item->staff->fname . ' ' . $item->staff->lname : 'Unknown',
                    'staff_email' => $item->staff ? $item->staff->email : 'Unknown',
                    'staff_role' => $item->staff ? $item->staff->role : 'Unknown',
                    'total_approvals' => $item->total_approvals,
                    'avg_response_time_hours' => round($item->avg_response_time_hours ?? 0, 2)
                ];
            });

        // Role-based performance
        $rolePerformance = ExeatApproval::with('staff:id,role')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->join('staff', 'exeat_approvals.staff_id', '=', 'staff.id')
            ->select(
                'staff.role',
                DB::raw('count(*) as total_approvals'),
                DB::raw('sum(case when exeat_approvals.status = "approved" then 1 else 0 end) as approved_count'),
                DB::raw('AVG(TIMESTAMPDIFF(HOUR, exeat_approvals.created_at, exeat_approvals.updated_at)) as avg_response_time_hours')
            )
            ->groupBy('staff.role')
            ->get()
            ->map(function($item) {
                return [
                    'role' => $item->role,
                    'total_approvals' => $item->total_approvals,
                    'approved_count' => $item->approved_count,
                    'approval_rate' => $item->total_approvals > 0 ? round(($item->approved_count / $item->total_approvals) * 100, 2) : 0,
                    'avg_response_time_hours' => round($item->avg_response_time_hours ?? 0, 2)
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'staff_performance' => $staffPerformance,
                'fastest_staff' => $fastestStaff,
                'role_performance' => $rolePerformance,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]
        ]);
    }
}
