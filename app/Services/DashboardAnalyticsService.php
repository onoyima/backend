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
}
