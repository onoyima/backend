<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\DashboardAnalyticsService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    protected $analyticsService;

    public function __construct(DashboardAnalyticsService $analyticsService)
    {
        $this->middleware('auth:sanctum');
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get admin dashboard data with comprehensive analytics
     */
    public function adminDashboard(Request $request): JsonResponse
    {
        $timeframe = $request->get('timeframe', '30'); // days
        
        $data = [
            'overview' => $this->analyticsService->getSystemOverview(),
            'exeat_statistics' => $this->analyticsService->getExeatStatistics($timeframe),
            'user_analytics' => $this->analyticsService->getUserAnalytics($timeframe),
            'performance_metrics' => $this->analyticsService->getPerformanceMetrics($timeframe),
            'debt_analytics' => $this->analyticsService->getDebtAnalytics($timeframe),
            'audit_trail' => $this->analyticsService->getAuditTrail($timeframe, 50),
            'audit_statistics' => $this->analyticsService->getAuditStatistics($timeframe),
            'charts' => [
                'exeat_trends' => $this->analyticsService->getExeatTrendsChart($timeframe),
                'status_distribution' => $this->analyticsService->getStatusDistributionChart($timeframe),
                'user_activity' => $this->analyticsService->getUserActivityChart($timeframe),
                'approval_rates' => $this->analyticsService->getApprovalRatesChart($timeframe),
                'debt_trends' => $this->analyticsService->getDebtTrendsChart($timeframe),
                'payment_methods' => $this->analyticsService->getPaymentMethodsStats($timeframe),
                'debt_aging' => $this->analyticsService->getDebtAgingAnalysis()
            ],
            'debt_summary' => [
                'top_debtors' => $this->analyticsService->getTopDebtors(10),
                'monthly_summary' => $this->analyticsService->getMonthlyDebtSummary(),
                'clearance_stats' => $this->analyticsService->getDebtClearanceStats($timeframe)
            ],
            'recent_activities' => $this->analyticsService->getRecentActivities(10)
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get dean dashboard data with department-specific analytics
     */
    public function deanDashboard(Request $request): JsonResponse
    {
        $user = Auth::user();
        $timeframe = $request->get('timeframe', '30');
        
        $data = [
            'overview' => $this->analyticsService->getDeanOverview($user->id),
            'department_statistics' => $this->analyticsService->getDepartmentStatistics($user->id, $timeframe),
            'pending_approvals' => $this->analyticsService->getPendingApprovals($user->id),
            'student_analytics' => $this->analyticsService->getStudentAnalytics($user->id, $timeframe),
            'debt_analytics' => $this->analyticsService->getDebtAnalytics($timeframe),
            'audit_trail' => $this->analyticsService->getDeanAuditTrail($user->id, $timeframe, 30),
            'charts' => [
                'department_trends' => $this->analyticsService->getDepartmentTrendsChart($user->id, $timeframe),
                'approval_timeline' => $this->analyticsService->getApprovalTimelineChart($user->id, $timeframe),
                'student_activity' => $this->analyticsService->getStudentActivityChart($user->id, $timeframe),
                'debt_trends' => $this->analyticsService->getDebtTrendsChart($timeframe),
                'payment_methods' => $this->analyticsService->getPaymentMethodsStats($timeframe)
            ],
            'debt_summary' => [
                'top_debtors' => $this->analyticsService->getTopDebtors(5),
                'clearance_stats' => $this->analyticsService->getDebtClearanceStats($timeframe)
            ],
            'recent_requests' => $this->analyticsService->getRecentDepartmentRequests($user->id, 15)
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get staff dashboard data with role-specific analytics
     */
    public function staffDashboard(Request $request): JsonResponse
    {
        $user = Auth::user();
        $timeframe = $request->get('timeframe', '30');
        
        $data = [
            'overview' => $this->analyticsService->getStaffOverview($user->id),
            'assigned_tasks' => $this->analyticsService->getAssignedTasks($user->id),
            'workload_statistics' => $this->analyticsService->getWorkloadStatistics($user->id, $timeframe),
            'charts' => [
                'task_completion' => $this->analyticsService->getTaskCompletionChart($user->id, $timeframe),
                'workload_trends' => $this->analyticsService->getWorkloadTrendsChart($user->id, $timeframe)
            ],
            'recent_activities' => $this->analyticsService->getStaffRecentActivities($user->id, 10)
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get security dashboard data for security staff
     */
    public function securityDashboard(Request $request): JsonResponse
    {
        $user = Auth::user();
        $timeframe = $request->get('timeframe', '7'); // Default to 7 days for security
        
        $data = [
            'overview' => $this->analyticsService->getSecurityOverview(),
            'active_exeats' => $this->analyticsService->getActiveExeats(),
            'sign_in_out_statistics' => $this->analyticsService->getSignInOutStatistics($timeframe),
            'charts' => [
                'daily_movements' => $this->analyticsService->getDailyMovementsChart($timeframe),
                'peak_hours' => $this->analyticsService->getPeakHoursChart($timeframe)
            ],
            'recent_movements' => $this->analyticsService->getRecentMovements(20)
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get housemaster dashboard data
     */
    public function housemasterDashboard(Request $request): JsonResponse
    {
        $user = Auth::user();
        $timeframe = $request->get('timeframe', '30');
        
        $data = [
            'overview' => $this->analyticsService->getHousemasterOverview($user->id),
            'house_statistics' => $this->analyticsService->getHouseStatistics($user->id, $timeframe),
            'student_welfare' => $this->analyticsService->getStudentWelfareMetrics($user->id, $timeframe),
            'charts' => [
                'house_activity' => $this->analyticsService->getHouseActivityChart($user->id, $timeframe),
                'student_behavior' => $this->analyticsService->getStudentBehaviorChart($user->id, $timeframe)
            ],
            'recent_house_activities' => $this->analyticsService->getRecentHouseActivities($user->id, 15)
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get common dashboard widgets that can be used across roles
     */
    public function getWidgets(Request $request): JsonResponse
    {
        $user = Auth::user();
        $widgets = $request->get('widgets', []);
        
        $data = [];
        
        foreach ($widgets as $widget) {
            switch ($widget) {
                case 'notifications':
                    $data['notifications'] = $this->analyticsService->getUserNotifications($user->id, 5);
                    break;
                case 'quick_stats':
                    $data['quick_stats'] = $this->analyticsService->getQuickStats($user->id);
                    break;
                case 'calendar':
                    $data['calendar'] = $this->analyticsService->getCalendarEvents($user->id);
                    break;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get paginated audit trail for admin users
     */
    public function getPaginatedAuditTrail(Request $request): JsonResponse
    {
        $timeframe = $request->get('timeframe', '30');
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);
        $action = $request->get('action');
        $targetType = $request->get('target_type');
        $staffId = $request->get('staff_id');
        $studentId = $request->get('student_id');

        $auditTrail = $this->analyticsService->getPaginatedAuditTrail(
            $timeframe, 
            $page, 
            $perPage, 
            $action, 
            $targetType, 
            $staffId, 
            $studentId
        );

        return response()->json([
            'success' => true,
            'data' => $auditTrail,
            'filters' => [
                'available_actions' => $this->analyticsService->getAvailableActions(),
                'available_target_types' => $this->analyticsService->getAvailableTargetTypes()
            ]
        ]);
    }

    /**
     * Get paginated dean audit trail for dean users
     */
    public function getPaginatedDeanAuditTrail(Request $request): JsonResponse
    {
        $user = Auth::user();
        $timeframe = $request->get('timeframe', '30');
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);
        $action = $request->get('action');
        $targetType = $request->get('target_type');
        $studentId = $request->get('student_id');

        $auditTrail = $this->analyticsService->getPaginatedDeanAuditTrail(
            $user->id,
            $timeframe, 
            $page, 
            $perPage, 
            $action, 
            $targetType, 
            $studentId
        );

        return response()->json([
            'success' => true,
            'data' => $auditTrail,
            'filters' => [
                'available_actions' => $this->analyticsService->getAvailableActions(),
                'available_target_types' => $this->analyticsService->getAvailableTargetTypes()
            ]
        ]);
    }
}