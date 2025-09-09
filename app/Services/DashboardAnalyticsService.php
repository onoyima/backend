<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\ExeatRequest;
use App\Models\Staff;
use App\Models\Student;
use App\Models\ParentConsent;
use App\Models\AuditLog;

class DashboardAnalyticsService
{
    /**
     * Get system overview for admin dashboard
     */
    public function getSystemOverview(): array
    {
        return Cache::remember('admin_system_overview', 300, function () {
            return [
                // 'total_students' => Student::count(),
                'total_students' => ExeatRequest::distinct('student_id')->count('student_id'),
                'total_staff' => Staff::whereHas('exeatRoles.role', function ($q) {
                    $q->whereIn('name', ['dean', 'housemaster', 'security', 'admin']);
                })->count(),
                // 'active_exeats' => ExeatRequest::whereIn('status', ['approved', 'signed_out'])->count(),
                'active_exeats' => ExeatRequest::whereNotIn('status', ['completed', 'rejected'])->count(),
                'approved_exeats' => ExeatRequest::where('status', 'completed')->count(),
                'student_away' => ExeatRequest::where('status', 'security_signin')->count(),
                // 'pending_approvals' => ExeatRequest::where('status', 'pending')->count(),
                'total_requests_today' => ExeatRequest::whereDate('created_at', today())->count(),
                'system_uptime' => '99.9%', // This would be calculated from monitoring data
            ];
        });
    }

    /**
     * Get exeat statistics for specified timeframe
     */
    public function getExeatStatistics(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        return Cache::remember("exeat_stats_{$days}d", 300, function () use ($startDate) {
            $totalRequests = ExeatRequest::where('created_at', '>=', $startDate)->count();
            $approvedRequests = ExeatRequest::where('created_at', '>=', $startDate)
                ->where('status', 'approved')->count();
            $rejectedRequests = ExeatRequest::where('created_at', '>=', $startDate)
                ->where('status', 'rejected')->count();

            return [
                'total_requests' => $totalRequests,
                'approved_requests' => $approvedRequests,
                'rejected_requests' => $rejectedRequests,
                'pending_requests' => ExeatRequest::where('status', 'pending')->count(),
                'approval_rate' => $totalRequests > 0 ? round(($approvedRequests / $totalRequests) * 100, 2) : 0,
                'average_processing_time' => $this->getAverageProcessingTime($startDate),
            ];
        });
    }

    /**
     * Get user analytics
     */
    public function getUserAnalytics(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        return [
            'active_users' => Staff::where('updated_at', '>=', $startDate)->count() +
                Student::where('updated_at', '>=', $startDate)->count(),
            'new_registrations' => Staff::where('created_at', '>=', $startDate)->count() +
                Student::where('created_at', '>=', $startDate)->count(),
            'role_distribution' => $this->getRoleDistribution(),
        ];
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(int $days = 30): array
    {
        return [
            'average_response_time' => '250ms', // From monitoring
            'system_load' => '45%', // From monitoring
            'database_queries_per_minute' => 1250, // From monitoring
            'cache_hit_rate' => '89%', // From monitoring
        ];
    }

    /**
     * Get exeat trends chart data
     */
    public function getExeatTrendsChart(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        $trends = ExeatRequest::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved'),
            DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected')
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'labels' => $trends->pluck('date')->map(function ($date) {
                return Carbon::parse($date)->format('M d');
            })->toArray(),
            'datasets' => [
                [
                    'label' => 'Total Requests',
                    'data' => $trends->pluck('total')->toArray(),
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)'
                ],
                [
                    'label' => 'Approved',
                    'data' => $trends->pluck('approved')->toArray(),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)'
                ],
                [
                    'label' => 'Rejected',
                    'data' => $trends->pluck('rejected')->toArray(),
                    'borderColor' => '#EF4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)'
                ]
            ]
        ];
    }

    /**
     * Get status distribution chart
     */
    public function getStatusDistributionChart(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        $distribution = ExeatRequest::select('status', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->groupBy('status')
            ->get();

        return [
            'labels' => $distribution->pluck('status')->map('ucfirst')->toArray(),
            'data' => $distribution->pluck('count')->toArray(),
            'backgroundColor' => [
                '#10B981', // Approved - Green
                '#EF4444', // Rejected - Red
                '#F59E0B', // Pending - Yellow
                '#3B82F6', // Signed Out - Blue
                '#8B5CF6', // Returned - Purple
            ]
        ];
    }

    /**
     * Get user activity chart
     */
    public function getUserActivityChart(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        // Combine activity from both Staff and Student models using updated_at as activity indicator
        $staffActivity = Staff::select(
            DB::raw('DATE(updated_at) as date'),
            DB::raw('COUNT(DISTINCT id) as active_users')
        )
            ->where('updated_at', '>=', $startDate)
            ->groupBy('date')
            ->get();

        $studentActivity = Student::select(
            DB::raw('DATE(updated_at) as date'),
            DB::raw('COUNT(DISTINCT id) as active_users')
        )
            ->where('updated_at', '>=', $startDate)
            ->groupBy('date')
            ->get();

        // Merge and sum the activities by date
        $activity = collect();
        $allDates = $staffActivity->pluck('date')->merge($studentActivity->pluck('date'))->unique();

        foreach ($allDates as $date) {
            $staffCount = $staffActivity->where('date', $date)->first()->active_users ?? 0;
            $studentCount = $studentActivity->where('date', $date)->first()->active_users ?? 0;
            $activity->push((object)[
                'date' => $date,
                'active_users' => $staffCount + $studentCount
            ]);
        }

        $activity = $activity->sortBy('date');

        return [
            'labels' => $activity->pluck('date')->map(function ($date) {
                return Carbon::parse($date)->format('M d');
            })->toArray(),
            'data' => $activity->pluck('active_users')->toArray(),
            'borderColor' => '#8B5CF6',
            'backgroundColor' => 'rgba(139, 92, 246, 0.1)'
        ];
    }

    /**
     * Get approval rates chart
     */
    public function getApprovalRatesChart(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        $rates = ExeatRequest::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved')
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                $item->rate = $item->total > 0 ? round(($item->approved / $item->total) * 100, 2) : 0;
                return $item;
            });

        return [
            'labels' => $rates->pluck('date')->map(function ($date) {
                return Carbon::parse($date)->format('M d');
            })->toArray(),
            'data' => $rates->pluck('rate')->toArray(),
            'borderColor' => '#F59E0B',
            'backgroundColor' => 'rgba(245, 158, 11, 0.1)'
        ];
    }

    /**
     * Get recent activities
     */
    public function getRecentActivities(int $limit = 10): array
    {
        return ExeatRequest::with(['student', 'approvals.staff'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($request) {
                $latestApproval = $request->approvals->where('status', 'approved')->last();
                return [
                    'id' => $request->id,
                    'student_name' => ($request->student->fname ?? '') . ' ' . ($request->student->lname ?? ''),
                    'status' => $request->status,
                    'created_at' => $request->created_at->diffForHumans(),
                    'approved_by' => $latestApproval && $latestApproval->staff ? ($latestApproval->staff->fname . ' ' . $latestApproval->staff->lname) : null,
                ];
            })
            ->toArray();
    }

    /**
     * Get dean-specific overview
     */
    public function getDeanOverview(int $deanId): array
    {
        // Assuming dean is associated with specific students/department
        return [
            'department_students' => $this->getDepartmentStudentCount($deanId),
            'pending_approvals' => $this->getPendingApprovalsCount($deanId),
            'approved_today' => $this->getApprovedTodayCount($deanId),
            'department_approval_rate' => $this->getDepartmentApprovalRate($deanId),
        ];
    }

    /**
     * Get department statistics
     */
    public function getDepartmentStatistics(int $deanId, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        // This would need to be adjusted based on your actual department/dean relationship
        return [
            'total_requests' => ExeatRequest::where('created_at', '>=', $startDate)->count(),
            'average_processing_time' => '2.5 hours',
            'most_active_day' => 'Friday',
        ];
    }

    /**
     * Get pending approvals for dean
     */
    public function getPendingApprovals(int $deanId): array
    {
        return ExeatRequest::with('student')
            ->where('status', 'pending')
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($request) {
                return [
                    'id' => $request->id,
                    'student_name' => $request->student->full_name ?? 'Unknown',
                    'reason' => $request->reason,
                    'created_at' => $request->created_at->diffForHumans(),
                ];
            })
            ->toArray();
    }

    /**
     * Get staff overview
     */
    public function getStaffOverview(int $staffId): array
    {
        return [
            'assigned_tasks' => 5, // This would be calculated based on staff role
            'completed_today' => 3,
            'pending_tasks' => 2,
            'efficiency_score' => 92,
        ];
    }

    /**
     * Get assigned tasks for staff
     */
    public function getAssignedTasks(int $staffId): array
    {
        // This would depend on the staff role and responsibilities
        return [
            ['task' => 'Review exeat requests', 'count' => 5, 'priority' => 'high'],
            ['task' => 'Update student records', 'count' => 3, 'priority' => 'medium'],
            ['task' => 'Generate reports', 'count' => 2, 'priority' => 'low'],
        ];
    }

    /**
     * Get security overview
     */
    public function getSecurityOverview(): array
    {
        return [
            'students_out' => ExeatRequest::where('status', 'signed_out')->count(),
            'expected_returns_today' => ExeatRequest::where('status', 'signed_out')
                ->whereDate('return_date', today())->count(),
            'overdue_returns' => ExeatRequest::where('status', 'signed_out')
                ->where('return_date', '<', now())->count(),
            'sign_ins_today' => ExeatRequest::whereDate('return_date', today())->count(),
        ];
    }

    /**
     * Get active exeats for security
     */
    public function getActiveExeats(): array
    {
        return ExeatRequest::with('student')
            ->where('status', 'signed_out')
            ->get()
            ->map(function ($request) {
                return [
                    'id' => $request->id,
                    'student_name' => $request->student->full_name ?? 'Unknown',
                    'student_id' => $request->student->student_id ?? 'Unknown',
                    'expected_return' => $request->return_date,
                    'is_overdue' => $request->return_date < now(),
                ];
            })
            ->toArray();
    }

    // Helper methods
    private function getAverageProcessingTime(Carbon $startDate): string
    {
        $avgMinutes = ExeatRequest::where('created_at', '>=', $startDate)
            ->whereIn('status', ['approved', 'completed', 'rejected'])
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_minutes')
            ->value('avg_minutes');

        if (!$avgMinutes) return '0 minutes';

        $hours = floor($avgMinutes / 60);
        $minutes = $avgMinutes % 60;

        return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
    }

    private function getRoleDistribution(): array
    {
        // Get role distribution from Staff model (which has exeat roles)
        $staffRoles = Staff::join('staff_exeat_roles', 'staff.id', '=', 'staff_exeat_roles.staff_id')
            ->join('exeat_roles', 'staff_exeat_roles.exeat_role_id', '=', 'exeat_roles.id')
            ->select('exeat_roles.name', DB::raw('COUNT(*) as count'))
            ->groupBy('exeat_roles.name')
            ->pluck('count', 'name')
            ->toArray();

        // Add student count as a separate category
        $studentCount = Student::count();
        if ($studentCount > 0) {
            $staffRoles['student'] = $studentCount;
        }

        return $staffRoles;
    }

    private function getDepartmentStudentCount(int $deanId): int
    {
        // Dean of Student Affairs oversees all students
        return Student::count();
    }

    private function getPendingApprovalsCount(int $deanId): int
    {
        // Dean of Student Affairs sees all pending approvals
        return ExeatRequest::where('status', 'pending')->count();
    }

    private function getApprovedTodayCount(int $deanId): int
    {
        // Dean of Student Affairs sees all approvals from today
        return ExeatRequest::whereIn('status', ['approved', 'completed'])
            ->whereDate('updated_at', today())
            ->count();
    }

    private function getDepartmentApprovalRate(int $deanId): float
    {
        // Dean of Student Affairs sees system-wide approval rate
        $total = ExeatRequest::count();
        $approved = ExeatRequest::where('status', 'approved')->count();

        return $total > 0 ? round(($approved / $total) * 100, 2) : 0;
    }

    // Additional methods for other dashboard types would be implemented here
    public function getStudentAnalytics(int $deanId, int $days)
    { /* Implementation */
    }
    public function getDepartmentTrendsChart(int $deanId, int $days)
    { /* Implementation */
    }
    public function getApprovalTimelineChart(int $deanId, int $days)
    { /* Implementation */
    }
    public function getStudentActivityChart(int $deanId, int $days)
    { /* Implementation */
    }
    public function getRecentDepartmentRequests(int $deanId, int $limit)
    { /* Implementation */
    }
    public function getWorkloadStatistics(int $staffId, int $days)
    { /* Implementation */
    }
    public function getTaskCompletionChart(int $staffId, int $days)
    { /* Implementation */
    }
    public function getWorkloadTrendsChart(int $staffId, int $days)
    { /* Implementation */
    }
    public function getStaffRecentActivities(int $staffId, int $limit)
    { /* Implementation */
    }
    public function getSignInOutStatistics(int $days)
    { /* Implementation */
    }
    public function getDailyMovementsChart(int $days)
    { /* Implementation */
    }
    public function getPeakHoursChart(int $days)
    { /* Implementation */
    }
    public function getRecentMovements(int $limit)
    { /* Implementation */
    }
    public function getHousemasterOverview(int $housemasterId)
    { /* Implementation */
    }
    public function getHouseStatistics(int $housemasterId, int $days)
    { /* Implementation */
    }
    public function getStudentWelfareMetrics(int $housemasterId, int $days)
    { /* Implementation */
    }
    public function getHouseActivityChart(int $housemasterId, int $days)
    { /* Implementation */
    }
    public function getStudentBehaviorChart(int $housemasterId, int $days)
    { /* Implementation */
    }
    public function getRecentHouseActivities(int $housemasterId, int $limit)
    { /* Implementation */
    }
    public function getUserNotifications(int $userId, int $limit)
    { /* Implementation */
    }
    public function getQuickStats(int $userId)
    { /* Implementation */
    }
    public function getCalendarEvents(int $userId)
    { /* Implementation */
    }

    /**
     * Get sanitized audit trail for admin dashboard
     */
    public function getAuditTrail(int $days = 30, int $limit = 50): array
    {
        $startDate = Carbon::now()->subDays($days);

        return Cache::remember("audit_trail_{$days}d_{$limit}", 300, function () use ($startDate, $limit) {
            $auditLogs = AuditLog::with(['staff:id,fname,lname,email', 'student:id,fname,lname'])
                ->where('created_at', '>=', $startDate)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'action' => $this->sanitizeAction($log->action),
                        'target_type' => $log->target_type,
                        'target_id' => $log->target_id,
                        'actor' => $this->getActorInfo($log),
                        'timestamp' => $log->created_at->format('Y-m-d H:i:s'),
                        'details' => $this->sanitizeDetails($log->details),
                        'formatted_time' => $log->created_at->diffForHumans(),
                    ];
                });

            return [
                'audit_logs' => $auditLogs,
                'total_actions' => AuditLog::where('created_at', '>=', $startDate)->count(),
                'action_summary' => $this->getActionSummary($startDate),
            ];
        });
    }

    /**
     * Get audit trail for dean dashboard (shows all activities like admin)
     */
    public function getDeanAuditTrail(int $deanId, int $days = 30, int $limit = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        return Cache::remember("dean_audit_trail_{$deanId}_{$days}d_{$limit}", 300, function () use ($deanId, $startDate, $limit) {
            // Get all audit logs (same as admin view)
            $auditLogs = AuditLog::with(['staff:id,fname,lname,email', 'student:id,fname,lname'])
                ->where('created_at', '>=', $startDate)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'action' => $this->sanitizeAction($log->action),
                        'target_type' => $log->target_type,
                        'target_id' => $log->target_id,
                        'actor' => $this->getActorInfo($log),
                        'timestamp' => $log->created_at->format('Y-m-d H:i:s'),
                        'details' => $this->sanitizeDetails($log->details),
                        'formatted_time' => $log->created_at->diffForHumans(),
                    ];
                });

            return [
                'audit_logs' => $auditLogs,
                'total_actions' => AuditLog::where('created_at', '>=', $startDate)->count(),
                'action_summary' => $this->getActionSummary($startDate),
            ];
        });
    }

    /**
     * Get audit statistics for charts
     */
    public function getAuditStatistics(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        return Cache::remember("audit_stats_{$days}d", 300, function () use ($startDate) {
            $dailyActivity = AuditLog::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total_actions'),
                DB::raw('COUNT(DISTINCT staff_id) as active_staff')
            )
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            $actionTypes = AuditLog::select('action', DB::raw('COUNT(*) as count'))
                ->where('created_at', '>=', $startDate)
                ->groupBy('action')
                ->orderBy('count', 'desc')
                ->get();

            return [
                'daily_activity_chart' => [
                    'labels' => $dailyActivity->pluck('date')->map(function ($date) {
                        return Carbon::parse($date)->format('M d');
                    })->toArray(),
                    'datasets' => [
                        [
                            'label' => 'Total Actions',
                            'data' => $dailyActivity->pluck('total_actions')->toArray(),
                            'borderColor' => '#3B82F6',
                            'backgroundColor' => 'rgba(59, 130, 246, 0.1)'
                        ],
                        [
                            'label' => 'Active Staff',
                            'data' => $dailyActivity->pluck('active_staff')->toArray(),
                            'borderColor' => '#10B981',
                            'backgroundColor' => 'rgba(16, 185, 129, 0.1)'
                        ]
                    ]
                ],
                'action_types_chart' => [
                    'labels' => $actionTypes->pluck('action')->map(function ($action) {
                        return $this->sanitizeAction($action);
                    })->toArray(),
                    'data' => $actionTypes->pluck('count')->toArray(),
                    'backgroundColor' => [
                        '#3B82F6',
                        '#10B981',
                        '#F59E0B',
                        '#EF4444',
                        '#8B5CF6',
                        '#EC4899'
                    ]
                ]
            ];
        });
    }

    /**
     * Sanitize action names for display
     */
    private function sanitizeAction(string $action): string
    {
        $actionMap = [
            'exeat_request_created' => 'Exeat Request Created',
            'exeat_request_approved' => 'Exeat Request Approved',
            'exeat_request_rejected' => 'Exeat Request Rejected',
            'exeat_request_signed_out' => 'Student Signed Out',
            'exeat_request_returned' => 'Student Returned',
            'parent_consent_approved' => 'Parent Consent Approved',
            'parent_consent_rejected' => 'Parent Consent Rejected',
            'staff_login' => 'Staff Login',
            'staff_logout' => 'Staff Logout',
            'profile_updated' => 'Profile Updated',
        ];

        return $actionMap[$action] ?? ucwords(str_replace('_', ' ', $action));
    }

    /**
     * Get actor information safely
     */
    private function getActorInfo($log): array
    {
        if ($log->staff) {
            return [
                'type' => 'staff',
                'name' => $log->staff->fname . ' ' . $log->staff->lname,
                'email' => $log->staff->email,
            ];
        }

        if ($log->student) {
            return [
                'type' => 'student',
                'name' => $log->student->fname . ' ' . $log->student->lname,
                'student_id' => $log->student->student_id,
            ];
        }

        return [
            'type' => 'system',
            'name' => 'System',
            'email' => null,
        ];
    }

    /**
     * Sanitize details for safe display
     */
    private function sanitizeDetails(?string $details): ?string
    {
        if (!$details) {
            return null;
        }

        // Remove sensitive information
        $sanitized = preg_replace('/password[\s]*[:=][\s]*[^\s,}]+/i', 'password: [REDACTED]', $details);
        $sanitized = preg_replace('/token[\s]*[:=][\s]*[^\s,}]+/i', 'token: [REDACTED]', $sanitized);
        $sanitized = preg_replace('/api_key[\s]*[:=][\s]*[^\s,}]+/i', 'api_key: [REDACTED]', $sanitized);

        return $sanitized;
    }

    /**
     * Get action summary for the specified period
     */
    private function getActionSummary(Carbon $startDate): array
    {
        return AuditLog::select('action', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get()
            ->mapWithKeys(function ($item) {
                return [$this->sanitizeAction($item->action) => $item->count];
            })
            ->toArray();
    }

    /**
     * Get department-specific action summary
     */
    private function getDepartmentActionSummary(int $deanId, Carbon $startDate): array
    {
        return AuditLog::select('action', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get()
            ->mapWithKeys(function ($item) {
                return [$this->sanitizeAction($item->action) => $item->count];
            })
            ->toArray();
    }
}
