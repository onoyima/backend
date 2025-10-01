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
use App\Models\StudentExeatDebt;

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
     * Get sanitized audit trail for admin dashboard with pagination
     */
    public function getAuditTrail(int $page = 1, int $perPage = 50): array
    {
        // Get paginated audit logs (all logs, no date filtering)
        $auditLogs = AuditLog::with(['staff:id,fname,lname,email', 'student:id,fname,lname'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $formattedLogs = $auditLogs->getCollection()->map(function ($log) {
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
            'audit_logs' => $formattedLogs,
            'pagination' => [
                'current_page' => $auditLogs->currentPage(),
                'last_page' => $auditLogs->lastPage(),
                'per_page' => $auditLogs->perPage(),
                'total' => $auditLogs->total(),
                'from' => $auditLogs->firstItem(),
                'to' => $auditLogs->lastItem(),
                'has_more_pages' => $auditLogs->hasMorePages(),
            ],
            'total_actions' => AuditLog::count(),
            'action_summary' => $this->getActionSummary(),
        ];
    }

    /**
     * Get audit trail for dean dashboard with pagination (shows all activities like admin)
     */
    public function getDeanAuditTrail(int $deanId, int $page = 1, int $perPage = 20): array
    {
        // Get paginated audit logs (all logs, no date filtering)
        $auditLogs = AuditLog::with(['staff:id,fname,lname,email', 'student:id,fname,lname'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $formattedLogs = $auditLogs->getCollection()->map(function ($log) {
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
            'audit_logs' => $formattedLogs,
            'pagination' => [
                'current_page' => $auditLogs->currentPage(),
                'last_page' => $auditLogs->lastPage(),
                'per_page' => $auditLogs->perPage(),
                'total' => $auditLogs->total(),
                'from' => $auditLogs->firstItem(),
                'to' => $auditLogs->lastItem(),
                'has_more_pages' => $auditLogs->hasMorePages(),
            ],
            'total_actions' => AuditLog::count(),
            'action_summary' => $this->getActionSummary(),
        ];
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
            'debt_created' => 'Debt Created',
            'debt_paid' => 'Debt Paid',
            'debt_cleared' => 'Debt Cleared'
        ];

        return $actionMap[$action] ?? ucwords(str_replace('_', ' ', $action));
    }

    /**
     * Get actor information from audit log
     */
    private function getActorInfo($log): array
    {
        if ($log->staff) {
            return [
                'type' => 'staff',
                'name' => $log->staff->fname . ' ' . $log->staff->lname,
                'id' => $log->staff->id
            ];
        } elseif ($log->student) {
            return [
                'type' => 'student',
                'name' => $log->student->fname . ' ' . $log->student->lname,
                'id' => $log->student->id
            ];
        }

        return [
            'type' => 'system',
            'name' => 'System',
            'id' => null
        ];
    }

    /**
     * Sanitize details for display
     */
    private function sanitizeDetails(?string $details): ?string
    {
        if (!$details) return null;
        
        // Remove sensitive information
        $details = preg_replace('/password["\s]*[:=]["\s]*[^,}]+/i', 'password: [HIDDEN]', $details);
        $details = preg_replace('/token["\s]*[:=]["\s]*[^,}]+/i', 'token: [HIDDEN]', $details);
        
        return $details;
    }

    /**
     * Get action summary for all audit logs
     */
    private function getActionSummary(?Carbon $startDate = null): array
    {
        $query = AuditLog::select('action', DB::raw('COUNT(*) as count'));
        
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        
        return $query->groupBy('action')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get()
            ->mapWithKeys(function ($item) {
                return [$this->sanitizeAction($item->action) => $item->count];
            })
            ->toArray();
    }

    /**
     * Get exeat statistics for specified timeframe
     */
    public function getExeatStatistics(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        $totalRequests = ExeatRequest::count();
        $approvedRequests = ExeatRequest::whereIn('status', ['completed', 'hostel_signin', 'security_signin', 'security_signout', 'hostel_signout'])->count();
        $completeRequests = ExeatRequest::where('status', 'completed')->count();
        $rejectedRequests = ExeatRequest::where('status', 'rejected')->count();
        $awaitingDeanApproval = ExeatRequest::where('status', 'dean_review')->count();
        $pending_requests = ExeatRequest::whereNotIn('status', ['completed', 'rejected', 'hostel_signin', 'security_signin', 'security_signout', 'hostel_signout'])->count();
        $student_outofschool = ExeatRequest::where('status', 'security_signin')->count();
        $parentRequestpending = ExeatRequest::where('status', 'secretary_review')->count();

        return [
            'total_requests' => $totalRequests,
            'approved_requests' => $approvedRequests,
            'rejected_requests' => $rejectedRequests,
            'pending_requests' => $pending_requests,
            'parentRequestpending' => $parentRequestpending,
            'completeRequests' => $completeRequests,
            'student_outofschool' => $student_outofschool,
            'awaitingDeanApproval' => $awaitingDeanApproval,
            'approval_rate' => $totalRequests > 0 ? round(($approvedRequests / $totalRequests) * 100, 2) : 0,
            'average_processing_time' => $this->getAverageProcessingTime($startDate),
        ];
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
            'average_response_time' => '250ms',
            'system_load' => '45%',
            'database_queries_per_minute' => 1250,
            'cache_hit_rate' => '89%',
        ];
    }

    /**
     * Get dean-specific overview
     */
    public function getDeanOverview(int $deanId): array
    {
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
                    'student_name' => $request->student->fname . ' ' . $request->student->lname ?? 'Unknown',
                    'reason' => $request->reason,
                    'created_at' => $request->created_at->diffForHumans(),
                ];
            })
            ->toArray();
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
     * Get debt analytics overview
     */
    public function getDebtAnalytics(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);
        
        $totalDebts = StudentExeatDebt::count();
        $totalAmount = StudentExeatDebt::sum('amount');
        $paidAmount = StudentExeatDebt::where('payment_status', 'paid')->sum('amount');
        $pendingAmount = StudentExeatDebt::where('payment_status', 'pending')->sum('amount');
        $clearedAmount = StudentExeatDebt::where('payment_status', 'cleared')->sum('amount');
        
        $recentDebts = StudentExeatDebt::where('created_at', '>=', $startDate)->count();
        $recentAmount = StudentExeatDebt::where('created_at', '>=', $startDate)->sum('amount');
        $recentPaid = StudentExeatDebt::where('created_at', '>=', $startDate)
            ->where('payment_status', 'paid')->sum('amount');
        
        $statusDistribution = StudentExeatDebt::select('payment_status', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('payment_status')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->payment_status => [
                    'count' => $item->count,
                    'amount' => $item->total_amount
                ]];
            })
            ->toArray();
        
        $avgDebtPerStudent = $totalDebts > 0 ? $totalAmount / $totalDebts : 0;
        $collectionRate = $totalAmount > 0 ? (($paidAmount + $clearedAmount) / $totalAmount) * 100 : 0;
        
        return [
            'overview' => [
                'total_debts' => $totalDebts,
                'total_amount' => number_format($totalAmount, 2),
                'paid_amount' => number_format($paidAmount, 2),
                'pending_amount' => number_format($pendingAmount, 2),
                'cleared_amount' => number_format($clearedAmount, 2),
                'average_debt' => number_format($avgDebtPerStudent, 2),
                'collection_rate' => number_format($collectionRate, 2),
            ],
            'recent_activity' => [
                'period_days' => $days,
                'new_debts' => $recentDebts,
                'new_amount' => number_format($recentAmount, 2),
                'recent_payments' => number_format($recentPaid, 2),
            ],
            'status_distribution' => $statusDistribution,
        ];
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
        $staffRoles = Staff::join('staff_exeat_roles', 'staff.id', '=', 'staff_exeat_roles.staff_id')
            ->join('exeat_roles', 'staff_exeat_roles.exeat_role_id', '=', 'exeat_roles.id')
            ->select('exeat_roles.name', DB::raw('COUNT(*) as count'))
            ->groupBy('exeat_roles.name')
            ->pluck('count', 'name')
            ->toArray();

        $studentCount = Student::count();
        if ($studentCount > 0) {
            $staffRoles['student'] = $studentCount;
        }

        return $staffRoles;
    }

    private function getDepartmentStudentCount(int $deanId): int
    {
        return Student::count();
    }

    private function getPendingApprovalsCount(int $deanId): int
    {
        return ExeatRequest::where('status', 'pending')->count();
    }

    private function getApprovedTodayCount(int $deanId): int
    {
        return ExeatRequest::whereIn('status', ['approved', 'completed'])
            ->whereDate('updated_at', today())
            ->count();
    }

    private function getDepartmentApprovalRate(int $deanId): float
    {
        $total = ExeatRequest::count();
        $approved = ExeatRequest::where('status', 'approved')->count();

        return $total > 0 ? round(($approved / $total) * 100, 2) : 0;
    }

    // Chart methods
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

    public function getUserActivityChart(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

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

    public function getDebtTrendsChart(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);
        $endDate = Carbon::now();
        
        $trends = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            $dayStart = $currentDate->copy()->startOfDay();
            $dayEnd = $currentDate->copy()->endOfDay();
            
            $dailyDebts = StudentExeatDebt::whereBetween('created_at', [$dayStart, $dayEnd])->count();
            $dailyAmount = StudentExeatDebt::whereBetween('created_at', [$dayStart, $dayEnd])->sum('amount');
            $dailyPayments = StudentExeatDebt::whereBetween('updated_at', [$dayStart, $dayEnd])
                ->whereIn('payment_status', ['paid', 'cleared'])
                ->sum('amount');
            
            $trends[] = [
                'date' => $currentDate->format('Y-m-d'),
                'new_debts' => $dailyDebts,
                'debt_amount' => $dailyAmount,
                'payments' => $dailyPayments,
                'formatted_date' => $currentDate->format('M d'),
            ];
            
            $currentDate->addDay();
        }
        
        return $trends;
    }

    public function getPaymentMethodsStats(int $days = 30): array
    {
        $paymentMethods = StudentExeatDebt::whereNotNull('payment_reference')
            ->where('payment_status', '!=', 'pending')
            ->select(DB::raw('
                CASE 
                    WHEN payment_reference LIKE "PAYSTACK%" THEN "Paystack"
                    WHEN payment_reference LIKE "MANUAL%" THEN "Manual Clearance"
                    ELSE "Other"
                END as method
            '), DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('method')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->method => [
                    'count' => $item->count,
                    'amount' => $item->total_amount
                ]];
            })
            ->toArray();
        
        return $paymentMethods;
    }

    public function getDebtAgingAnalysis(): array
    {
        $now = Carbon::now();
        
        $agingBrackets = [
            '0-30 days' => [0, 30],
            '31-60 days' => [31, 60],
            '61-90 days' => [61, 90],
            '91+ days' => [91, 9999],
        ];
        
        $agingData = [];
        
        foreach ($agingBrackets as $bracket => $range) {
            $startDate = $now->copy()->subDays($range[1]);
            $endDate = $range[0] > 0 ? $now->copy()->subDays($range[0]) : $now;
            
            $query = StudentExeatDebt::where('payment_status', 'pending');
            
            if ($range[1] < 9999) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            } else {
                $query->where('created_at', '<=', $startDate);
            }
            
            $count = $query->count();
            $amount = $query->sum('amount');
            
            $agingData[$bracket] = [
                'count' => $count,
                'amount' => $amount,
                'formatted_amount' => number_format($amount, 2),
            ];
        }
        
        return $agingData;
    }

    public function getTopDebtors(int $limit = 10): array
    {
        return StudentExeatDebt::with(['student:id,fname,lname,email,username'])
            ->select('student_id', DB::raw('COUNT(*) as debt_count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('student_id')
            ->orderBy('total_amount', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($debt) {
                return [
                    'student_id' => $debt->student_id,
                    'student_name' => $debt->student ? $debt->student->fname . ' ' . $debt->student->lname : 'Unknown',
                    'student_number' => $debt->student ? $debt->student->username : 'N/A',
                    'debt_count' => $debt->debt_count,
                    'total_amount' => number_format($debt->total_amount, 2),
                ];
            })
            ->toArray();
    }

    public function getMonthlyDebtSummary(int $months = 12): array
    {
        $startDate = Carbon::now()->subMonths($months)->startOfMonth();
        
        $monthlyData = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate <= Carbon::now()) {
            $monthStart = $currentDate->copy()->startOfMonth();
            $monthEnd = $currentDate->copy()->endOfMonth();
            
            $monthlyDebts = StudentExeatDebt::whereBetween('created_at', [$monthStart, $monthEnd])->count();
            $monthlyAmount = StudentExeatDebt::whereBetween('created_at', [$monthStart, $monthEnd])->sum('amount');
            $monthlyPayments = StudentExeatDebt::whereBetween('updated_at', [$monthStart, $monthEnd])
                ->whereIn('payment_status', ['paid', 'cleared'])
                ->sum('amount');
            
            $monthlyData[] = [
                'month' => $currentDate->format('Y-m'),
                'month_name' => $currentDate->format('M Y'),
                'new_debts' => $monthlyDebts,
                'debt_amount' => $monthlyAmount,
                'payments' => $monthlyPayments,
                'net_change' => $monthlyAmount - $monthlyPayments,
            ];
            
            $currentDate->addMonth();
        }
        
        return $monthlyData;
    }

    public function getDebtClearanceStats(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);
        
        $clearanceByStaff = StudentExeatDebt::with(['clearedByStaff:id,fname,lname'])
            ->where('payment_status', 'cleared')
            ->where('updated_at', '>=', $startDate)
            ->whereNotNull('cleared_by')
            ->get()
            ->groupBy(function ($debt) {
                return $debt->clearedByStaff ? ($debt->clearedByStaff->fname . ' ' . $debt->clearedByStaff->lname) : 'Unknown';
            })
            ->map(function ($debts, $staffName) {
                return [
                    'staff' => $staffName,
                    'count' => $debts->count(),
                    'amount' => $debts->sum('amount'),
                ];
            })
            ->values()
            ->toArray();
        
        $clearedDebts = StudentExeatDebt::where('payment_status', 'cleared')
            ->where('updated_at', '>=', $startDate)
            ->get();
        
        $avgClearanceTime = 0;
        if ($clearedDebts->count() > 0) {
            $totalHours = $clearedDebts->sum(function ($debt) {
                return $debt->created_at->diffInHours($debt->updated_at);
            });
            $avgClearanceTime = $totalHours / $clearedDebts->count();
        }
        
        return [
            'clearance_by_staff' => $clearanceByStaff,
            'average_clearance_time_hours' => number_format($avgClearanceTime, 2),
            'total_cleared' => $clearedDebts->count(),
            'total_cleared_amount' => number_format($clearedDebts->sum('amount'), 2),
        ];
    }

    public function getRecentActivities(int $limit = 10): array
    {
        return Cache::remember("recent_activities_{$limit}", 300, function () use ($limit) {
            return AuditLog::with(['staff:id,fname,lname', 'student:id,fname,lname'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($log) {
                    $actorInfo = $this->getActorInfo($log);
                    return [
                        'id' => $log->id,
                        'action' => $this->sanitizeAction($log->action),
                        'actor' => $actorInfo['name'],
                        'actor_type' => $actorInfo['type'],
                        'actor_id' => $actorInfo['id'],
                        'timestamp' => $log->created_at->format('Y-m-d H:i:s'),
                        'formatted_time' => $log->created_at->diffForHumans(),
                        'details' => $this->sanitizeDetails($log->details),
                    ];
                })
                ->toArray();
        });
    }

    // Additional helper methods

     // Stub methods for other dashboard types
     public function getStudentAnalytics(int $deanId, int $days) { return []; }
    public function getDepartmentTrendsChart(int $deanId, int $days) { return []; }
    public function getApprovalTimelineChart(int $deanId, int $days) { return []; }
    public function getStudentActivityChart(int $deanId, int $days) { return []; }
    public function getRecentDepartmentRequests(int $deanId, int $limit) { return []; }
    public function getStaffOverview(int $staffId) { return []; }
    public function getAssignedTasks(int $staffId) { return []; }
    public function getWorkloadStatistics(int $staffId, int $days) { return []; }
    public function getTaskCompletionChart(int $staffId, int $days) { return []; }
    public function getWorkloadTrendsChart(int $staffId, int $days) { return []; }
    public function getStaffRecentActivities(int $staffId, int $limit) { return []; }
    public function getSecurityOverview() { return []; }
    public function getActiveExeats() { return []; }
    public function getSignInOutStatistics(int $days) { return []; }
    public function getDailyMovementsChart(int $days) { return []; }
    public function getPeakHoursChart(int $days) { return []; }
    public function getRecentMovements(int $limit) { return []; }
    public function getHousemasterOverview(int $housemasterId) { return []; }
    public function getHouseStatistics(int $housemasterId, int $days) { return []; }
    public function getStudentWelfareMetrics(int $housemasterId, int $days) { return []; }
    public function getHouseActivityChart(int $housemasterId, int $days) { return []; }
    public function getStudentBehaviorChart(int $housemasterId, int $days) { return []; }
    public function getRecentHouseActivities(int $housemasterId, int $limit) { return []; }
    public function getUserNotifications(int $userId, int $limit) { return []; }
    public function getQuickStats(int $userId) { return []; }
    public function getCalendarEvents(int $userId) { return []; }
    public function getPaginatedAuditTrail(int $timeframe, int $page, int $perPage, ?string $action, ?string $targetType, ?int $staffId, ?int $studentId) { return []; }
    public function getPaginatedDeanAuditTrail(int $deanId, int $timeframe, int $page, int $perPage, ?string $action, ?string $targetType, ?int $studentId) { return []; }
    public function getAvailableActions() { return []; }
    public function getAvailableTargetTypes() { return []; }
}
