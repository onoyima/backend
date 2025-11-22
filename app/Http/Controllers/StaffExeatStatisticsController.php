<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\ExeatRequest;
use App\Models\ExeatApproval;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StaffExeatStatisticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get comprehensive exeat statistics for staff
     */
    public function getExeatStatistics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Get total counts
            $totalPending = ExeatRequest::where('status', 'pending')->count();
            $totalApproved = ExeatRequest::whereIn('status', ['completed', 'hostel_signout', 'security_signout', 'security_signin', 'hostel_signin'])->count();
            $totalRejected = ExeatRequest::where('status', 'rejected')->count();
            $totalSignedOut = ExeatRequest::whereIn('status', ['security_signout', 'hostel_signin'])->count();
            $totalSignedIn = ExeatRequest::where('status', 'completed')->count();

            // Get role-specific statistics
            $roleStats = $this->getRoleSpecificStatistics();

            // Get recent activity (last 30 days)
            $recentActivity = ExeatRequest::where('created_at', '>=', Carbon::now()->subDays(30))
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->limit(30)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => [
                        'total_pending' => $totalPending,
                        'total_approved' => $totalApproved,
                        'total_rejected' => $totalRejected,
                        'total_signed_out' => $totalSignedOut,
                        'total_signed_in' => $totalSignedIn,
                    ],
                    'role_statistics' => $roleStats,
                    'recent_activity' => $recentActivity,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get role-specific statistics
     */
    private function getRoleSpecificStatistics(): array
    {
        $roles = ['cmd', 'secretary', 'dean', 'hostel_admin', 'security'];
        $roleStats = [];

        foreach ($roles as $role) {
            $pending = ExeatApproval::where('role', $role)
                ->where('status', 'pending')
                ->count();

            $approved = ExeatApproval::where('role', $role)
                ->where('status', 'approved')
                ->count();

            $rejected = ExeatApproval::where('role', $role)
                ->where('status', 'rejected')
                ->count();

            $roleStats[$role] = [
                'pending' => $pending,
                'approved' => $approved,
                'rejected' => $rejected,
                'total' => $pending + $approved + $rejected
            ];
        }

        return $roleStats;
    }

    /**
     * Get detailed statistics with filters
     */
    public function getDetailedStatistics(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'role' => 'nullable|string|in:cmd,secretary,dean,hostel_admin,security',
                'status' => 'nullable|string|in:pending,approved,rejected,completed'
            ]);

            $query = ExeatRequest::query();

            // Apply date filters
            if ($request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Apply role filter through approvals
            if ($request->role) {
                $query->whereHas('approvals', function ($q) use ($request) {
                    $q->where('role', $request->role);
                });
            }

            // Apply status filter
            if ($request->status) {
                $query->where('status', $request->status);
            }

            // Get statistics
            $totalRequests = $query->count();
            $statusBreakdown = $query->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $categoryBreakdown = $query->join('exeat_categories', 'exeat_requests.category_id', '=', 'exeat_categories.id')
                ->selectRaw('exeat_categories.name as category, COUNT(*) as count')
                ->groupBy('exeat_categories.name')
                ->pluck('count', 'category')
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_requests' => $totalRequests,
                    'status_breakdown' => $statusBreakdown,
                    'category_breakdown' => $categoryBreakdown,
                    'filters_applied' => [
                        'date_from' => $request->date_from,
                        'date_to' => $request->date_to,
                        'role' => $request->role,
                        'status' => $request->status
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch detailed statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get staff exeat history - all exeat requests a staff has performed actions on
     */
    public function getStaffExeatHistory(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $request->validate([
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'status' => 'nullable|string',
                'role' => 'nullable|string'
            ]);

            $perPage = $request->get('per_page', 15);

            // Get all exeat requests where this staff has performed any action
            $query = ExeatRequest::whereHas('approvals', function ($q) use ($user) {
                $q->where('staff_id', $user->id);
            })
            ->with([
                'student:id,fname,lname',
                'category:id,name',
                'approvals' => function ($q) use ($user) {
                    $q->where('staff_id', $user->id)
                      ->orderBy('created_at', 'desc');
                }
            ])
            ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->role) {
                $query->whereHas('approvals', function ($q) use ($user, $request) {
                    $q->where('staff_id', $user->id)
                      ->where('role', $request->role);
                });
            }

            $exeatHistory = $query->paginate($perPage);

            // Transform the data to include action details
            $transformedData = $exeatHistory->getCollection()->map(function ($exeat) {
                $staffApprovals = $exeat->approvals;

                return [
                    'id' => $exeat->id,
                    'student' => [
                        'id' => $exeat->student->id,
                        'name' => $exeat->student->fname . ' ' . $exeat->student->lname
                    ],
                    'category' => $exeat->category->name ?? 'N/A',
                    'reason' => $exeat->reason,
                    'status' => $exeat->status,
                    'departure_date' => $exeat->departure_date,
                    'return_date' => $exeat->return_date,
                    'created_at' => $exeat->created_at,
                    'updated_at' => $exeat->updated_at,
                    'actions_performed' => $staffApprovals->map(function ($approval) {
                        return [
                            'role' => $approval->role,
                            'method' => $approval->method,
                            'comment' => $approval->comment,
                            'approved_at' => $approval->approved_at,
                            'created_at' => $approval->created_at
                        ];
                    })
                ];
            });

            // Get summary statistics for this staff
            $totalActionsCount = ExeatApproval::where('staff_id', $user->id)->count();
            $roleBreakdown = ExeatApproval::where('staff_id', $user->id)
                ->selectRaw('role, COUNT(*) as count')
                ->groupBy('role')
                ->pluck('count', 'role')
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'exeat_history' => $transformedData,
                    'pagination' => [
                        'current_page' => $exeatHistory->currentPage(),
                        'last_page' => $exeatHistory->lastPage(),
                        'per_page' => $exeatHistory->perPage(),
                        'total' => $exeatHistory->total(),
                        'from' => $exeatHistory->firstItem(),
                        'to' => $exeatHistory->lastItem()
                    ],
                    'summary' => [
                        'total_actions_performed' => $totalActionsCount,
                        'total_exeat_requests_acted_on' => $exeatHistory->total(),
                        'role_breakdown' => $roleBreakdown
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch staff exeat history',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
