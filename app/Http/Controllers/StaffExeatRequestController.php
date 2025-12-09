<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExeatApproval;
use App\Models\ExeatRequest;
use App\Models\AuditLog;
use App\Models\StudentExeatDebt;
use Illuminate\Support\Facades\Log;
use App\Services\ExeatWorkflowService;
use App\Http\Requests\StaffExeatApprovalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Dompdf\Dompdf;

class StaffExeatRequestController extends Controller
{
    protected $workflowService;

    protected $notificationService;

    public function __construct(
        ExeatWorkflowService $workflowService,
        \App\Services\ExeatNotificationService $notificationService
    ) {
        $this->workflowService = $workflowService;
        $this->notificationService = $notificationService;
        $this->middleware('auth:sanctum');
    }

    private function getAllowedStatuses(array $roleNames)
    {
        // Define all workflow statuses (excluding completed, rejected, and appeal)
        $activeStatuses = [
            'pending',
            'cmd_review',
            'secretary_review',
            'parent_consent',
            'dean_review',
            'hostel_signout',
            'security_signout',
            'security_signin',
            'hostel_signin',
            'cancelled'
        ];

        $roleStatusMap = [
            'cmd' => ['cmd_review'],
            'secretary' => ['secretary_review', 'parent_consent'],
            'dean' => $activeStatuses, // Dean can see all active statuses
            'deputy-dean' => $activeStatuses, // Deputy-dean can see all active statuses
            'admin' => $activeStatuses, // Admin can see all active statuses
            'hostel_admin' => ['hostel_signout', 'hostel_signin'],
            'security' => ['security_signout', 'security_signin'],
        ];

        $allowedStatuses = [];

        foreach ($roleNames as $role) {
            if (isset($roleStatusMap[$role])) {
                $allowedStatuses = array_merge($allowedStatuses, $roleStatusMap[$role]);
            } else {
                Log::notice('Role not mapped to statuses', ['role' => $role]);
            }
        }

        return array_unique($allowedStatuses);
    }

    private function getActingRole($user, $currentStatus)
    {
        $roleMap = [
            'cmd_review' => 'cmd',
            'secretary_review' => 'secretary',
            'dean_review' => 'dean',
            'hostel_signout' => 'hostel_admin',
            'hostel_signin' => 'hostel_admin',
            'security_signout' => 'security',
            'security_signin' => 'security',
        ];

        $roles = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();

        // If user has admin role, they can act as any role based on current status
        if (in_array('admin', $roles) && isset($roleMap[$currentStatus])) {
            return $roleMap[$currentStatus];
        }

        // Special handling for deputy-dean - can act on ALL statuses with appropriate role mapping
        if (in_array('deputy-dean', $roles) && isset($roleMap[$currentStatus])) {
            return $roleMap[$currentStatus]; // deputy-dean can act as any required role for any status
        }

        // For non-admin users, check if they have the specific role for the current status
        foreach ($roles as $role) {
            if (isset($roleMap[$currentStatus]) && $roleMap[$currentStatus] === $role) {
                return $role;
            }
        }

        return 'unknown';
    }

    /**
     * Apply hostel-based filtering for hostel admins
     */
    private function applyHostelFiltering($query, $user, $roleNames)
    {
        // If user is dean, deputy-dean, or admin, they can see all exeat requests
        if (array_intersect(['dean', 'deputy-dean', 'admin'], $roleNames)) {
            return $query;
        }

        // If user has hostel_admin role, filter by assigned hostels
        if (in_array('hostel_admin', $roleNames)) {
            $assignedHostels = \App\Models\HostelAdminAssignment::where('staff_id', $user->id)
                ->where('status', 'active')
                ->with('hostel')
                ->get();

            if ($assignedHostels->isNotEmpty()) {
                $hostelNames = $assignedHostels->pluck('hostel.name')->toArray();
                $query->whereIn('student_accommodation', $hostelNames);
            } else {
                // If hostel admin has no assignments, they see nothing
                $query->where('id', -1); // Impossible condition to return empty result
            }
        }

        return $query;
    }

    /**
     * Check if user has access to specific exeat request based on hostel assignment
     */
    private function hasHostelAccess($user, $exeatRequest, $roleNames)
    {
        // If user is dean, deputy-dean, or admin, they have access to all
        if (array_intersect(['dean', 'deputy-dean', 'admin'], $roleNames)) {
            return true;
        }

        // If user has hostel_admin role, check hostel assignment
        if (in_array('hostel_admin', $roleNames)) {
            $assignedHostels = \App\Models\HostelAdminAssignment::where('staff_id', $user->id)
                ->where('status', 'active')
                ->with('hostel')
                ->get();

            if ($assignedHostels->isEmpty()) {
                return false; // No hostel assignments
            }

            $hostelNames = $assignedHostels->pluck('hostel.name')->toArray();
            return in_array($exeatRequest->student_accommodation, $hostelNames);
        }

        // For other roles, default access rules apply
        return true;
    }

    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if (!($user instanceof \App\Models\Staff)) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
            $allowedStatuses = $this->getAllowedStatuses($roleNames);

            if (empty($allowedStatuses) && !$request->has('student_id')) {
                return response()->json(['message' => 'No access to exeat requests.'], 403);
            }

            $perPage = (int) ($request->input('per_page', 50));
            $page = (int) ($request->input('page', 1));
            $search = trim((string) $request->input('search', ''));
            $categoryId = $request->input('category_id');
            $statusFilter = $request->input('status');

            // Optional filter: search by student_id – if provided, ignore status restrictions
            if ($request->has('student_id')) {
                $query = ExeatRequest::query()
                    ->with('student:id,fname,lname,passport,phone')
                    ->where('student_id', $request->input('student_id'));
            } elseif ($search !== '') {
                // If searching, ignore status restrictions (Global Search Mode) to show full history
                $query = ExeatRequest::query()
                    ->with('student:id,fname,lname,passport,phone');
            } else {
                $query = ExeatRequest::query()
                    ->with('student:id,fname,lname,passport,phone')
                    ->whereIn('status', $allowedStatuses);
            }

            // Apply hostel-based filtering for hostel admins
            $query = $this->applyHostelFiltering($query, $user, $roleNames);

            if ($request->has('status') && $statusFilter !== 'all') { // Only apply if explicitly filtered
                $query->where('status', $request->input('status'));
            }

            if ($request->has('filter')) {
                // ... (existing filter checks) ...
                $filter = $request->input('filter');
                if ($filter === 'overdue') {
                    $query->where('return_date', '<', now()->toDateString())
                        ->where('is_expired', false)
                        ->whereNotIn('status', ['security_signin', 'hostel_signin', 'completed', 'rejected']);
                } elseif ($filter === 'signed_out') {
                    $query->where('status', 'security_signout');
                } elseif ($filter === 'signed_in') {
                    $query->where('status', 'security_signin');
                }
            }

            // Category filter
            if ($categoryId !== null && $categoryId !== '') {
                $query->where('category_id', (int) $categoryId);
            }

            // Global search logic (applied to the query object we initialized above)
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('matric_no', 'like', "%{$search}%")
                        ->orWhere('destination', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhere('parent_surname', 'like', "%{$search}%")
                        ->orWhere('parent_othernames', 'like', "%{$search}%")
                        ->orWhereHas('student', function ($sq) use ($search) {
                            $sq->where('fname', 'like', "%{$search}%")
                                ->orWhere('lname', 'like', "%{$search}%");
                        });
                });
            }

            // Pagination and sorting
            if ($search !== '' || $request->has('student_id')) {
                // If searching or viewing student history, prioritize latest Created At (newest requests first)
                $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
            } else {
                // Default inbox view: sort by departure date (soonest departures first)
                $query->orderBy('departure_date', 'asc')->orderBy('id', 'asc');
            }

            // Ensure perPage is valid
            if ($perPage <= 0)
                $perPage = 20;

            // Fetch records with pagination to prevent memory exhaustion
            $exeatRequests = $query->paginate($perPage);

            // Transform status for display if expired
            $exeatRequests->getCollection()->transform(function ($item) {
                if ($item->is_expired) {
                    $item->status = 'expired';
                }
                return $item;
            });

            return response()->json([
                'exeat_requests' => $exeatRequests->items(),
                'pagination' => [
                    'current_page' => $exeatRequests->currentPage(),
                    'last_page' => $exeatRequests->lastPage(),
                    'per_page' => $exeatRequests->perPage(),
                    'total' => $exeatRequests->total(),
                    'from' => $exeatRequests->firstItem(),
                    'to' => $exeatRequests->lastItem(),
                ]
            ]);
        } catch (\Throwable $e) {
            \Log::error('StaffExeatRequestController@index failed', [
                'error' => $e->getMessage(),
                'query_params' => $request->all(),
                'user_id' => optional($request->user())->id
            ]);
            return response()->json([
                'exeat_requests' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => (int) ($request->input('per_page', 50)),
                    'total' => 0,
                    'from' => null,
                    'to' => null
                ],
                'message' => 'Server Error'
            ], 200);
        }
    }

    public function export(Request $request)
    {
        try {
            $user = $request->user();
            if (!($user instanceof \App\Models\Staff)) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
            $allowedStatuses = $this->getAllowedStatuses($roleNames);

            $query = ExeatRequest::query()->with('student:id,fname,lname,passport,phone');
            if (!$request->has('student_id')) {
                $query->whereIn('status', $allowedStatuses);
            } else {
                $query->where('student_id', $request->input('student_id'));
            }

            $query = $this->applyHostelFiltering($query, $user, $roleNames);

            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->has('filter')) {
                $filter = $request->input('filter');
                if ($filter === 'overdue') {
                    $query->where('return_date', '<', now()->toDateString())
                        ->where('is_expired', false)
                        ->whereNotIn('status', ['security_signin', 'hostel_signin', 'completed', 'rejected']);
                } elseif ($filter === 'signed_out') {
                    $query->where('status', 'security_signout');
                } elseif ($filter === 'signed_in') {
                    $query->where('status', 'security_signin');
                }
            }

            $rows = $query->orderBy('departure_date', 'asc')->get()->map(function ($r) {
                $name = $r->student ? (($r->student->fname ?? '') . ' ' . ($r->student->lname ?? '')) : '';
                return [
                    $r->matric_no,
                    $name,
                    $r->student_accommodation,
                    $r->status,
                    $r->departure_date,
                    $r->return_date,
                ];
            });

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="exeat_requests.csv"'
            ];

            $callback = function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['matric_no', 'student_name', 'hostel', 'status', 'departure_date', 'return_date']);
                foreach ($rows as $row) {
                    fputcsv($out, $row);
                }
                fclose($out);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Throwable $e) {
            \Log::error('StaffExeatRequestController@export failed', [
                'error' => $e->getMessage(),
                'query_params' => $request->all(),
                'user_id' => optional($request->user())->id
            ]);
            return response()->json(['message' => 'Server Error'], 200);
        }
    }

    public function gateEvents(Request $request)
    {
        try {
            $user = $request->user();
            if (!($user instanceof \App\Models\Staff)) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
            if (!(in_array('admin', $roleNames) || in_array('hostel_admin', $roleNames))) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $perPage = (int) ($request->input('per_page', 20));
            $page = (int) ($request->input('page', 1));
            $checked = $request->input('checked'); // 'in' | 'out' | 'all'
            $search = trim((string) $request->input('search', ''));

            $query = \DB::table('exeat_requests')
                ->leftJoin('security_signouts', 'security_signouts.exeat_request_id', '=', 'exeat_requests.id')
                ->leftJoin('students', 'students.id', '=', 'exeat_requests.student_id')
                ->select([
                    'exeat_requests.id as exeat_id',
                    'exeat_requests.matric_no',
                    'students.fname',
                    'students.lname',
                    'exeat_requests.student_accommodation as hostel',
                    'security_signouts.signout_time',
                    'security_signouts.signin_time',
                    'exeat_requests.departure_date',
                    'exeat_requests.return_date'
                ]);

            // Restrict to hostel admin assigned hostels
            if (!in_array('admin', $roleNames) && in_array('hostel_admin', $roleNames)) {
                $assignedHostels = \App\Models\HostelAdminAssignment::where('staff_id', $user->id)
                    ->where('status', 'active')
                    ->with('hostel')
                    ->get();
                if ($assignedHostels->isEmpty()) {
                    return response()->json(['message' => 'Unauthorized.'], 403);
                }
                $hostelNames = $assignedHostels->pluck('hostel.name')->toArray();
                $query->whereIn('exeat_requests.student_accommodation', $hostelNames);
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('exeat_requests.matric_no', 'like', "%{$search}%")
                        ->orWhere('students.fname', 'like', "%{$search}%")
                        ->orWhere('students.lname', 'like', "%{$search}%");
                });
            }

            if ($checked === 'in') {
                $query->whereNotNull('security_signouts.signin_time');
            } elseif ($checked === 'out') {
                $query->whereNotNull('security_signouts.signout_time')
                    ->whereNull('security_signouts.signin_time');
            }

            // Sorting
            $sortBy = $request->input('sort_by', 'security_signouts.signout_time');
            $order = strtolower($request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
            $allowedSort = [
                'exeat_requests.matric_no',
                'students.fname',
                'security_signouts.signout_time',
                'security_signouts.signin_time',
                'exeat_requests.departure_date',
                'exeat_requests.return_date'
            ];
            if (!in_array($sortBy, $allowedSort)) {
                $sortBy = 'security_signouts.signout_time';
            }
            $query->orderBy($sortBy, $order);

            // Pagination
            $total = (clone $query)->count();
            $items = $query->forPage($page, $perPage)->get();

            return response()->json([
                'data' => $items,
                'pagination' => [
                    'current_page' => $page,
                    'last_page' => (int) ceil($total / $perPage),
                    'per_page' => $perPage,
                    'total' => $total,
                    'from' => ($total === 0) ? null : (($page - 1) * $perPage + 1),
                    'to' => ($total === 0) ? null : min($page * $perPage, $total)
                ]
            ]);
        } catch (\Throwable $e) {
            \Log::error('StaffExeatRequestController@gateEvents failed', [
                'error' => $e->getMessage(),
                'query_params' => $request->all(),
                'user_id' => optional($request->user())->id
            ]);
            return response()->json([
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => (int) ($request->input('per_page', 20)),
                    'total' => 0,
                    'from' => null,
                    'to' => null
                ]
            ]);
        }
    }

    public function gateEventsExport(Request $request)
    {
        try {
            $user = $request->user();
            if (!($user instanceof \App\Models\Staff)) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
            $checked = $request->input('checked');
            $search = trim((string) $request->input('search', ''));
            $format = strtolower((string) $request->input('format', 'csv'));
            if (!(in_array('admin', $roleNames) || in_array('hostel_admin', $roleNames))) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $query = \DB::table('exeat_requests')
                ->leftJoin('security_signouts', 'security_signouts.exeat_request_id', '=', 'exeat_requests.id')
                ->leftJoin('students', 'students.id', '=', 'exeat_requests.student_id')
                ->select([
                    'exeat_requests.matric_no',
                    'students.fname',
                    'students.lname',
                    'exeat_requests.student_accommodation as hostel',
                    'security_signouts.signout_time',
                    'security_signouts.signin_time',
                    'exeat_requests.departure_date',
                    'exeat_requests.return_date'
                ]);

            if (!in_array('admin', $roleNames) && in_array('hostel_admin', $roleNames)) {
                $assignedHostels = \App\Models\HostelAdminAssignment::where('staff_id', $user->id)
                    ->where('status', 'active')
                    ->with('hostel')
                    ->get();
                if ($assignedHostels->isEmpty()) {
                    return response()->json(['message' => 'Unauthorized.'], 403);
                } else {
                    $hostelNames = $assignedHostels->pluck('hostel.name')->toArray();
                    $query->whereIn('exeat_requests.student_accommodation', $hostelNames);
                    if ($search !== '') {
                        $query->where(function ($q) use ($search) {
                            $q->where('exeat_requests.matric_no', 'like', "%{$search}%")
                                ->orWhere('students.fname', 'like', "%{$search}%")
                                ->orWhere('students.lname', 'like', "%{$search}%");
                        });
                    }
                    if ($checked === 'in') {
                        $query->whereNotNull('security_signouts.signin_time');
                    } elseif ($checked === 'out') {
                        $query->whereNotNull('security_signouts.signout_time')
                            ->whereNull('security_signouts.signin_time');
                    }
                    $rows = $query->orderBy('security_signouts.signout_time', 'desc')->get();
                }
            } else {
                if ($search !== '') {
                    $query->where(function ($q) use ($search) {
                        $q->where('exeat_requests.matric_no', 'like', "%{$search}%")
                            ->orWhere('students.fname', 'like', "%{$search}%")
                            ->orWhere('students.lname', 'like', "%{$search}%");
                    });
                }
                if ($checked === 'in') {
                    $query->whereNotNull('security_signouts.signin_time');
                } elseif ($checked === 'out') {
                    $query->whereNotNull('security_signouts.signout_time')
                        ->whereNull('security_signouts.signin_time');
                }
                $rows = $query->orderBy('security_signouts.signout_time', 'desc')->get();
            }
            if ($format === 'xls') {
                $html = '<table border="1" cellspacing="0" cellpadding="4">'
                    . '<thead><tr>'
                    . '<th>matric_no</th><th>student_name</th><th>hostel</th><th>checked_out</th><th>checked_in</th><th>departure_date</th><th>return_date</th><th>actual_returned_date</th>'
                    . '</tr></thead><tbody>';
                foreach ($rows as $r) {
                    $name = trim(($r->fname ?? '') . ' ' . ($r->lname ?? ''));
                    $html .= '<tr>'
                        . '<td>' . htmlspecialchars($r->matric_no ?? '') . '</td>'
                        . '<td>' . htmlspecialchars($name) . '</td>'
                        . '<td>' . htmlspecialchars($r->hostel ?? '') . '</td>'
                        . '<td>' . htmlspecialchars($r->signout_time ?? '') . '</td>'
                        . '<td>' . htmlspecialchars($r->signin_time ?? '') . '</td>'
                        . '<td>' . htmlspecialchars($r->departure_date ?? '') . '</td>'
                        . '<td>' . htmlspecialchars($r->return_date ?? '') . '</td>'
                        . '<td>' . htmlspecialchars($r->signin_time ?? '') . '</td>'
                        . '</tr>';
                }
                $html .= '</tbody></table>';
                return response($html, 200, [
                    'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="gate_events.xls"'
                ]);
            } elseif ($format === 'pdf') {
                $html = '<html><head><meta charset="UTF-8"><style>table{width:100%;border-collapse:collapse}th,td{border:1px solid #333;padding:4px;font-size:12px}th{background:#eee;text-align:left}</style></head><body>'
                    . '<h3>Gate Events</h3>'
                    . '<table><thead><tr>'
                    . '<th>matric_no</th><th>student_name</th><th>hostel</th><th>checked_out</th><th>checked_in</th><th>departure_date</th><th>return_date</th><th>actual_returned_date</th>'
                    . '</tr></thead><tbody>';
                foreach ($rows as $r) {
                    $name = trim(($r->fname ?? '') . ' ' . ($r->lname ?? ''));
                    $html .= '<tr>'
                        . '<td>' . htmlspecialchars($r->matric_no ?? '') . '</td>'
                        . '<td>' . htmlspecialchars($name) . '</td>'
                        . '<td>' . htmlspecialchars($r->hostel ?? '') . '</td>'
                        . '<td>' . htmlspecialchars($r->signout_time ?? '') . '</td>'
                        . '<td>' . htmlspecialchars($r->signin_time ?? '') . '</td>'
                        . '<td>' . htmlspecialchars($r->departure_date ?? '') . '</td>'
                        . '<td>' . htmlspecialchars($r->return_date ?? '') . '</td>'
                        . '<td>' . htmlspecialchars($r->signin_time ?? '') . '</td>'
                        . '</tr>';
                }
                $html .= '</tbody></table></body></html>';
                $dompdf = new Dompdf();
                $dompdf->loadHtml($html, 'UTF-8');
                $dompdf->setPaper('A4', 'landscape');
                $dompdf->render();
                return response($dompdf->output(), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="gate_events.pdf"'
                ]);
            } else {
                $headers = [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="gate_events.csv"'
                ];
                $callback = function () use ($rows) {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, ['matric_no', 'student_name', 'hostel', 'checked_out', 'checked_in', 'departure_date', 'return_date', 'actual_returned_date']);
                    foreach ($rows as $r) {
                        $name = trim(($r->fname ?? '') . ' ' . ($r->lname ?? ''));
                        fputcsv($out, [
                            $r->matric_no,
                            $name,
                            $r->hostel,
                            $r->signout_time,
                            $r->signin_time,
                            $r->departure_date,
                            $r->return_date,
                            $r->signin_time,
                        ]);
                    }
                    fclose($out);
                };
                return response()->stream($callback, 200, $headers);
            }
        } catch (\Throwable $e) {
            \Log::error('StaffExeatRequestController@gateEventsExport failed', [
                'error' => $e->getMessage(),
                'query_params' => $request->all(),
                'user_id' => optional($request->user())->id
            ]);
            return response()->json(['message' => 'Server Error'], 200);
        }
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $approvalIds = ExeatApproval::where('staff_id', $user->id)->pluck('exeat_request_id');

        $totalRequests = ExeatRequest::whereIn('id', $approvalIds)->count();
        $pendingRequests = ExeatRequest::whereIn('id', $approvalIds)
            ->whereIn('status', ['pending', 'cmd_review', 'secretary_review', 'parent_consent', 'dean_review'])
            ->count();
        $approvedRequests = ExeatRequest::whereIn('id', $approvalIds)
            ->where('status', 'completed')
            ->count();
        $rejectedRequests = ExeatRequest::whereIn('id', $approvalIds)
            ->where('status', 'rejected')
            ->count();

        $data = [
            'total_requests' => $totalRequests,
            'pending_requests' => $pendingRequests,
            'approved_requests' => $approvedRequests,
            'rejected_requests' => $rejectedRequests,
        ];

        $userRoles = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        if (in_array('admin', $userRoles) || in_array('dean', $userRoles) || in_array('deputy-dean', $userRoles)) {
            $byStatus = DB::table('exeat_requests')
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->toArray();

            $byDepartment = DB::table('exeat_requests')
                ->join('students', 'exeat_requests.student_id', '=', 'students.id')
                ->join('student_academics', 'students.id', '=', 'student_academics.student_id')
                ->join('departments', 'student_academics.department_id', '=', 'departments.id')
                ->select('departments.name as department', DB::raw('count(*) as count'))
                ->groupBy('departments.name')
                ->get()
                ->toArray();

            $byDate = DB::table('exeat_requests')
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->toArray();

            $data['analytics'] = [
                'by_status' => $byStatus,
                'by_department' => $byDepartment,
                'by_date' => $byDate
            ];
        }

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $exeatRequest = ExeatRequest::with('student:id,fname,lname,passport,phone')->find($id);
        if (!$exeatRequest) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }

        $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        $allowedStatuses = $this->getAllowedStatuses($roleNames);

        if (!in_array($exeatRequest->status, $allowedStatuses)) {
            return response()->json(['message' => 'You do not have permission to view this request.'], 403);
        }

        // Check hostel-based access for hostel admins
        if (!$this->hasHostelAccess($user, $exeatRequest, $roleNames)) {
            return response()->json(['message' => 'You do not have permission to view this request from this hostel.'], 403);
        }

        return response()->json(['exeat_request' => $exeatRequest]);
    }

    /**
     * Edit an exeat request (staff only)
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(Request $request, $id)
    {
        // Validate input
        $validated = $request->validate([
            'category_id' => 'sometimes|required|exists:exeat_categories,id',
            'reason' => 'sometimes|required|string|max:500',
            'destination' => 'sometimes|required|string|max:255',
            'departure_date' => 'sometimes|required|date',
            'return_date' => 'sometimes|required|date|after_or_equal:departure_date',
            'actual_return_date' => 'sometimes|nullable|date',
            'status' => 'sometimes|required|string|in:pending,approved,rejected,completed',
            'is_medical' => 'sometimes|boolean',
            'comment' => 'nullable|string|max:1000'
        ]);

        // Validate ID format
        if (!is_numeric($id) || $id <= 0) {
            return response()->json(['message' => 'Invalid exeat request ID.'], 400);
        }

        $exeat = ExeatRequest::with(['student'])->find($id);
        if (!$exeat) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }

        $user = $request->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        $allowedStatuses = $this->getAllowedStatuses($roleNames);

        if (!in_array($exeat->status, $allowedStatuses)) {
            return response()->json(['message' => 'You do not have permission to edit this request.'], 403);
        }

        // Check if exeat can be edited
        if (
            in_array($exeat->status, ['revoked', 'rejected', 'cancelled']) &&
            (!isset($validated['status']) || $validated['status'] !== 'completed')
        ) {
            return response()->json([
                'message' => 'This exeat request cannot be edited as it is already ' . $exeat->status . '.'
            ], 409);
        }

        $changes = [];
        $actualReturnDateChanged = false;
        $oldActualReturnDate = $exeat->actual_return_date;

        DB::beginTransaction();
        try {
            // Track changes for audit log
            foreach ($validated as $field => $value) {
                if (isset($exeat->$field) && $exeat->$field != $value) {
                    $changes[$field] = [
                        'from' => $exeat->$field,
                        'to' => $value
                    ];

                    if ($field === 'actual_return_date') {
                        $actualReturnDateChanged = true;
                    }

                    $exeat->$field = $value;
                }
            }

            // Only save if there are changes
            if (!empty($changes)) {
                $exeat->save();

                // Recalculate debt if actual_return_date was changed
                if ($actualReturnDateChanged) {
                    $this->recalculateDebt($exeat, $oldActualReturnDate);
                }

                // Create audit log entry
                AuditLog::create([
                    'staff_id' => $user->id,
                    'student_id' => $exeat->student_id,
                    'action' => 'exeat_edited_by_staff',
                    'target_type' => 'exeat_request',
                    'target_id' => $exeat->id,
                    'details' => json_encode([
                        'changes' => $changes,
                        'comment' => $validated['comment'] ?? null
                    ]),
                    'timestamp' => now(),
                ]);

                // Send notification to student
                $this->notificationService->sendExeatModifiedNotification(
                    $exeat,
                    'Your exeat request has been modified by staff.'
                );

                DB::commit();

                return response()->json([
                    'message' => 'Exeat request updated successfully.',
                    'exeat_request' => $exeat->fresh(),
                    'changes' => $changes
                ]);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'No changes were made to the exeat request.'
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to edit exeat request', [
                'exeat_id' => $id,
                'staff_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to edit exeat request. Please try again.',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Recalculate student debt based on actual return date
     *
     * @param ExeatRequest $exeat
     * @param string|null $oldActualReturnDate
     * @return void
     */
    protected function recalculateDebt(ExeatRequest $exeat, $oldActualReturnDate)
    {
        // Skip if no actual return date is set
        if (!$exeat->actual_return_date) {
            return;
        }

        // Calculate days late using exact 24-hour periods at 11:59 PM
        $returnDate = \Carbon\Carbon::parse($exeat->return_date);
        $actualReturnDate = \Carbon\Carbon::parse($exeat->actual_return_date);
        $daysLate = $this->calculateDaysOverdue($returnDate, $actualReturnDate);

        // Only create/update debt if student returned late
        if ($daysLate > 0) {
            // Check for existing debt
            $debt = StudentExeatDebt::where('exeat_request_id', $exeat->id)->first();

            // Use standard fee of 10,000 per day
            $amount = $daysLate * 10000;

            if ($debt) {
                $oldAmount = $debt->amount;
                // Update existing debt
                $debt->update([
                    'amount' => $amount,
                ]);

                // Create audit log for debt update
                AuditLog::create([
                    'staff_id' => Auth::id(),
                    'student_id' => $exeat->student_id,
                    'action' => 'debt_updated_by_staff',
                    'target_type' => 'student_exeat_debt',
                    'target_id' => $debt->id,
                    'details' => json_encode([
                        'exeat_request_id' => $exeat->id,
                        'days_late' => $daysLate,
                        'new_amount' => $amount,
                        'old_amount' => $oldAmount,
                        'return_date' => $exeat->return_date,
                        'actual_return_date' => $exeat->actual_return_date,
                        'updated_by' => Auth::id()
                    ]),
                    'timestamp' => now(),
                ]);
            } else {
                // Create new debt
                $newDebt = StudentExeatDebt::create([
                    'student_id' => $exeat->student_id,
                    'exeat_request_id' => $exeat->id,
                    'amount' => $amount,
                    'payment_status' => 'unpaid',
                ]);

                // Create audit log for debt creation
                AuditLog::create([
                    'staff_id' => Auth::id(),
                    'student_id' => $exeat->student_id,
                    'action' => 'debt_created_by_staff',
                    'target_type' => 'student_exeat_debt',
                    'target_id' => $newDebt->id,
                    'details' => json_encode([
                        'exeat_request_id' => $exeat->id,
                        'days_late' => $daysLate,
                        'amount' => $amount,
                        'return_date' => $exeat->return_date,
                        'actual_return_date' => $exeat->actual_return_date,
                        'created_by' => Auth::id()
                    ]),
                    'timestamp' => now(),
                ]);
            }
        } else if ($oldActualReturnDate) {
            // If the actual return date was changed and is now on time, remove any existing debt
            $deletedDebts = StudentExeatDebt::where('exeat_request_id', $exeat->id)->get();

            foreach ($deletedDebts as $deletedDebt) {
                // Create audit log for debt removal
                AuditLog::create([
                    'staff_id' => Auth::id(),
                    'student_id' => $exeat->student_id,
                    'action' => 'debt_removed_by_staff',
                    'target_type' => 'student_exeat_debt',
                    'target_id' => $deletedDebt->id,
                    'details' => json_encode([
                        'exeat_request_id' => $exeat->id,
                        'removed_amount' => $deletedDebt->amount,
                        'reason' => 'Student returned on time after staff update',
                        'return_date' => $exeat->return_date,
                        'actual_return_date' => $exeat->actual_return_date,
                        'removed_by' => Auth::id()
                    ]),
                    'timestamp' => now(),
                ]);
            }

            StudentExeatDebt::where('exeat_request_id', $exeat->id)->delete();
        }
    }

    public function approve(StaffExeatApprovalRequest $request, $id)
    {
        $user = $request->user();

        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $exeatRequest = ExeatRequest::with('student:id,fname,lname,passport')->find($id);
        if (!$exeatRequest) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }

        $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        $allowedStatuses = $this->getAllowedStatuses($roleNames);

        if (!in_array($exeatRequest->status, $allowedStatuses)) {
            return response()->json(['message' => 'You do not have permission to approve this request at this stage.'], 403);
        }

        // ✅ Determine acting role
        $actingRole = $this->getActingRole($user, $exeatRequest->status);

        // ✅ Prevent duplicate approval for same role and request
        $alreadyApproved = ExeatApproval::where('exeat_request_id', $exeatRequest->id)
            ->where('staff_id', $user->id)
            ->where('role', $actingRole)
            ->where('method', $exeatRequest->status)
            ->exists();

        if ($alreadyApproved) {
            return response()->json(['message' => "You have already approved this request as '{$actingRole}'."], 409);
        }

        $validated = $request->validate(['comment' => 'nullable|string']);

        DB::beginTransaction();
        try {
            $approval = ExeatApproval::create([
                'exeat_request_id' => $exeatRequest->id,
                'staff_id' => $user->id,
                'status' => 'approved',
                'comment' => $validated['comment'] ?? null,
                'role' => $actingRole,
                'method' => $exeatRequest->status,
            ]);

            $this->workflowService->approve($exeatRequest, $approval, $approval->comment);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Approval failed: ' . $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Exeat request approved.', 'exeat_request' => $exeatRequest]);
    }




    public function reject(StaffExeatApprovalRequest $request, $id)
    {
        $user = $request->user();

        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $exeatRequest = ExeatRequest::with('student:id,fname,lname,passport')->find($id);
        if (!$exeatRequest) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }

        $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        $allowedStatuses = $this->getAllowedStatuses($roleNames);

        if (!in_array($exeatRequest->status, $allowedStatuses)) {
            return response()->json(['message' => 'You do not have permission to reject this request.'], 403);
        }

        // ✅ Determine acting role
        $actingRole = $this->getActingRole($user, $exeatRequest->status);

        // ✅ Prevent duplicate rejection for same role
        $alreadyActed = ExeatApproval::where('exeat_request_id', $exeatRequest->id)
            ->where('staff_id', $user->id)
            ->where('role', $actingRole)
            ->where('method', $exeatRequest->status)
            ->exists();

        if ($alreadyActed) {
            return response()->json(['message' => "You have already taken action on this request as '{$actingRole}'."], 409);
        }

        $validated = $request->validate(['comment' => 'required|string']);
        $workflow = $this->workflowService;

        DB::beginTransaction();
        try {
            $approval = ExeatApproval::create([
                'exeat_request_id' => $exeatRequest->id,
                'staff_id' => $user->id,
                'status' => 'rejected',
                'comment' => $validated['comment'],
                'role' => $actingRole,
                'method' => $exeatRequest->status,
            ]);

            $this->workflowService->reject($exeatRequest, $approval, $approval->comment);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Rejection failed: ' . $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Exeat request rejected.', 'exeat_request' => $exeatRequest]);
    }


    public function sendParentConsent(Request $request, $id)
    {
        $user = $request->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $exeatRequest = ExeatRequest::with('student:id,fname,lname,passport')->find($id);
        if (!$exeatRequest) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }

        // Optional: prevent triggering it at the wrong time
        if ($exeatRequest->status !== 'parent_consent') {
            return response()->json(['message' => 'Parent consent can only be sent at the parent_consent stage.'], 403);
        }

        $validated = $request->validate([
            'method' => 'required|string',
            'message' => 'nullable|string',
        ]);

        $parentConsent = $this->workflowService->sendParentConsent($exeatRequest, $validated['method'], $validated['message'] ?? null, $user->id);

        // Check notification status and provide appropriate response
        $responseMessage = 'Parent consent request processed.';
        $responseCode = 200;

        if (isset($parentConsent->notification_status)) {
            switch ($parentConsent->notification_status) {
                case 'success':
                    $responseMessage = 'Parent consent request sent successfully.';
                    break;
                case 'no_email':
                    $responseMessage = 'Parent consent request created, but no parent email address is available for email notification.';
                    $responseCode = 422;
                    break;
                case 'failed':
                    $responseMessage = 'Parent consent request created, but notification delivery failed.';
                    $responseCode = 422;
                    break;
            }
        }

        return response()->json([
            'message' => $responseMessage,
            'parent_consent' => $parentConsent,
            'notification_status' => $parentConsent->notification_status ?? 'unknown',
            'status_message' => $parentConsent->status_message ?? 'No status message available'
        ], $responseCode);
    }

    public function history(Request $request, $id)
    {
        $user = $request->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $exeatRequest = ExeatRequest::with('student:id,fname,lname,passport')->find($id);
        if (!$exeatRequest) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }

        $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        $allowedStatuses = $this->getAllowedStatuses($roleNames);

        if (!in_array($exeatRequest->status, $allowedStatuses)) {
            return response()->json(['message' => 'You do not have permission to view this history.'], 403);
        }

        $auditLogs = AuditLog::where('target_type', 'exeat_request')
            ->where('target_id', $id)
            ->orderBy('timestamp', 'desc')
            ->with(['staff:id,fname,lname', 'student:id,fname,lname,passport'])
            ->get();

        $approvals = ExeatApproval::where('exeat_request_id', $id)
            ->with('staff:id,fname,lname')
            ->orderBy('updated_at', 'desc')
            ->get();

        $history = [
            'audit_logs' => $auditLogs,
            'approvals' => $approvals,
            'exeat_request' => $exeatRequest
        ];

        return response()->json(['history' => $history]);
    }

    /**
     * Get role-based history - all exeat requests that have passed through the staff member's roles
     * Shows all requests that went through their role's review stage, regardless of who reviewed them
     */
    public function roleHistory(Request $request)
    {
        $user = $request->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();

        if (empty($roleNames)) {
            return response()->json(['message' => 'No roles assigned.'], 403);
        }

        // Define all possible statuses in the exeat workflow
        $allStatuses = [
            'pending',
            'cmd_review',
            'secretary_review',
            'parent_consent',
            'dean_review',
            'hostel_signout',
            'security_signout',
            'security_signin',
            'hostel_signin',
            'completed',
            'rejected',
            'appeal'
        ];

        // Map roles to their corresponding statuses that they handle
        $roleStatusMap = [
            'cmd' => ['cmd_review'],
            'secretary' => ['secretary_review'],
            'dean' => $allStatuses, // Dean can see all statuses
            'deputy-dean' => $allStatuses, // Deputy-dean can see all statuses
            'admin' => $allStatuses, // Admin can see all statuses
            'hostel_admin' => ['hostel_signout', 'hostel_signin'],
            'security' => ['security_signout', 'security_signin'],
        ];

        // Get all statuses that this staff member's roles handle
        $handledStatuses = [];
        foreach ($roleNames as $role) {
            if (isset($roleStatusMap[$role])) {
                $handledStatuses = array_merge($handledStatuses, $roleStatusMap[$role]);
            }
        }

        $handledStatuses = array_unique($handledStatuses);

        if (empty($handledStatuses)) {
            return response()->json(['message' => 'No handled statuses found for your roles.'], 403);
        }

        // Check if user has admin, dean, or deputy-dean role - they can see ALL requests
        $canSeeAllRequests = array_intersect(['admin', 'dean', 'deputy-dean'], $roleNames);

        if (!empty($canSeeAllRequests)) {
            // Admin, dean, and deputy-dean can see all exeat requests
            $allRequestIds = ExeatRequest::pluck('id')->toArray();
        } else {
            // Get all exeat requests that have passed through the workflow stages handled by this staff member's roles
            // This includes requests that have been at these statuses at any point, regardless of who processed them
            $requestsWithApprovals = ExeatApproval::whereIn('role', $roleNames)
                ->distinct()
                ->pluck('exeat_request_id')
                ->toArray();

            // Also get requests that are currently at or have passed through the statuses this staff can handle
            $requestsAtStatuses = ExeatRequest::whereIn('status', $handledStatuses)
                ->pluck('id')
                ->toArray();

            // Get requests that have audit logs showing they were at these statuses (passed through)
            $requestsFromAuditLogs = AuditLog::where('target_type', 'exeat_request')
                ->where(function ($query) use ($handledStatuses) {
                    foreach ($handledStatuses as $status) {
                        $query->orWhere('details', 'like', "%to {$status}%");
                    }
                })
                ->distinct()
                ->pluck('target_id')
                ->toArray();

            // Combine all request IDs to get comprehensive role history
            $allRequestIds = array_unique(array_merge($requestsWithApprovals, $requestsAtStatuses, $requestsFromAuditLogs));
        }

        if (empty($allRequestIds)) {
            return response()->json([
                'message' => 'No exeat requests found for your roles.',
                'exeat_requests' => [],
                'roles' => $roleNames,
                'handled_statuses' => $handledStatuses
            ]);
        }

        // Get the exeat requests with their complete approval history
        $exeatRequests = ExeatRequest::whereIn('id', $allRequestIds)
            ->with([
                'student:id,fname,lname,passport',
                'approvals' => function ($query) {
                    $query->with('staff:id,fname,lname')
                        ->orderBy('updated_at', 'desc');
                },
                'approvals.staff:id,fname,lname'
            ])
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        // Add role information to each request
        foreach ($exeatRequests as $request) {
            $request->staff_roles = $roleNames;
            $request->acting_roles = [];

            // Determine which roles this staff member could act as for this request
            foreach ($roleNames as $role) {
                if ($role === 'admin' || $role === 'dean') {
                    $request->acting_roles[] = $role;
                } elseif (isset($roleStatusMap[$role])) {
                    foreach ($roleStatusMap[$role] as $status) {
                        if (
                            $request->status === $status ||
                            (isset($request->status_history) && strpos($request->status_history, $status) !== false)
                        ) {
                            $request->acting_roles[] = $role;
                            break;
                        }
                    }
                }
            }
        }

        return response()->json([
            'message' => 'Role-based exeat history retrieved successfully.',
            'exeat_requests' => $exeatRequests,
            'staff_roles' => $roleNames,
            'handled_statuses' => $handledStatuses
        ]);
    }

    /**
     * Get all pending parent consents that Secretary can act upon.
     */
    public function getPendingParentConsents(Request $request)
    {
        $user = $request->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Check if staff has secretary role
        $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        if (!in_array('secretary', $roleNames)) {
            return response()->json([
                'message' => 'Unauthorized. Only Secretary can access parent consents.'
            ], 403);
        }

        $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'student_id' => 'integer|exists:students,id',
            'date_from' => 'date',
            'date_to' => 'date|after_or_equal:date_from'
        ]);

        $perPage = $request->get('per_page', 20);

        $query = \App\Models\ParentConsent::with(['exeatRequest.student'])
            ->where('consent_status', 'pending')
            ->whereHas('exeatRequest', function ($q) {
                $q->where('status', 'parent_consent');
            })
            ->orderBy('created_at', 'asc');

        if ($request->has('student_id')) {
            $query->whereHas('exeatRequest', function ($q) use ($request) {
                $q->where('student_id', $request->student_id);
            });
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $consents = $query->paginate($perPage);

        return response()->json([
            'message' => 'Pending parent consents retrieved successfully.',
            'data' => $consents->items(),
            'pagination' => [
                'current_page' => $consents->currentPage(),
                'last_page' => $consents->lastPage(),
                'per_page' => $consents->perPage(),
                'total' => $consents->total(),
                'from' => $consents->firstItem(),
                'to' => $consents->lastItem()
            ]
        ]);
    }

    /**
     * Secretary approves parent consent on behalf of parent.
     */
    public function approveParentConsent(Request $request, $consentId)
    {
        $user = $request->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Check if staff has secretary role
        $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        if (!in_array('secretary', $roleNames)) {
            return response()->json([
                'message' => 'Unauthorized. Only Secretary can approve parent consents.'
            ], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
            'notify_parent' => 'boolean'
        ]);

        $consent = \App\Models\ParentConsent::with('exeatRequest.student')->find($consentId);

        if (!$consent) {
            return response()->json([
                'message' => 'Parent consent not found'
            ], 404);
        }

        if ($consent->consent_status !== 'pending') {
            return response()->json([
                'message' => 'This consent request has already been processed'
            ], 400);
        }

        if ($consent->exeatRequest->status !== 'parent_consent') {
            return response()->json([
                'message' => 'Exeat request is not in parent consent stage'
            ], 400);
        }

        // Check if consent has expired
        if ($consent->expires_at && now()->gt($consent->expires_at)) {
            return response()->json([
                'message' => 'This consent link has expired'
            ], 410);
        }

        $workflow = $this->workflowService;

        DB::beginTransaction();
        try {
            // Update consent status
            $consent->update([
                'consent_status' => 'approved',
                'acted_by_staff_id' => $user->id,
                'action_type' => 'secretary_approval',
                'secretary_reason' => $request->reason,
                'consent_timestamp' => now()
            ]);

            // Move exeat request to next stage
            $exeatRequest = $consent->exeatRequest;
            $exeatRequest->update(['status' => 'dean_review']);

            // Create audit log
            AuditLog::create([
                'target_type' => 'exeat_request',
                'target_id' => $exeatRequest->id,
                'staff_id' => $user->id,
                'student_id' => $exeatRequest->student_id,
                'action' => 'secretary_parent_consent_approval',
                'details' => "Secretary approved parent consent on behalf of parent. Reason: {$request->reason}",
                'timestamp' => now()
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Approval failed: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Parent consent approved successfully on behalf of parent',
            'data' => [
                'consent' => $consent->fresh(),
                'exeat_request' => $exeatRequest->fresh()
            ]
        ]);
    }

    /**
     * Secretary rejects parent consent on behalf of parent.
     */
    /**
     * Send a comment to a student regarding their exeat request
     * This doesn't affect the exeat workflow status
     * Only one comment notification is allowed per exeat status
     */
    public function sendComment(Request $request, $id)
    {
        $user = $request->user();

        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $exeatRequest = ExeatRequest::with('student:id,fname,lname,passport')->find($id);
        if (!$exeatRequest) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }

        $validated = $request->validate([
            'comment' => 'required|string|max:500',
        ]);

        try {
            // Check if a comment notification has already been sent for the current status
            $currentStatus = $exeatRequest->status;
            $existingComment = \App\Models\ExeatNotification::where('exeat_request_id', $exeatRequest->id)
                ->where('notification_type', \App\Models\ExeatNotification::TYPE_STAFF_COMMENT)
                ->where('data->status', $currentStatus)
                ->first();

            if ($existingComment) {
                // Get the staff who sent the previous comment from audit log
                $previousCommentLog = AuditLog::where('target_type', 'exeat_request')
                    ->where('target_id', $exeatRequest->id)
                    ->where('action', 'staff_comment')
                    ->where('details', 'like', '%Staff sent comment to student:%')
                    ->with('staff:id,fname,lname')
                    ->orderBy('timestamp', 'desc')
                    ->first();

                $previousStaffName = 'Unknown Staff';
                $previousComment = 'Comment details not available';
                $sentAt = $existingComment->created_at->format('M j, Y g:i A');

                if ($previousCommentLog && $previousCommentLog->staff) {
                    $previousStaffName = "{$previousCommentLog->staff->fname} {$previousCommentLog->staff->lname}";
                    // Extract comment from audit log details
                    if (preg_match('/Staff sent comment to student: (.+)$/', $previousCommentLog->details, $matches)) {
                        $previousComment = $matches[1];
                    }
                }

                return response()->json([
                    'message' => 'A comment has already been sent for this exeat status. Please wait until the status changes to send another comment.',
                    'status' => $currentStatus,
                    'previous_comment' => [
                        'message' => $previousComment,
                        'sent_by' => $previousStaffName,
                        'sent_at' => $sentAt,
                        'notification_id' => $existingComment->id
                    ]
                ], 422);
            }

            // Send notification to student
            $notifications = $this->notificationService->sendStaffCommentNotification(
                $exeatRequest,
                $user,
                $validated['comment']
            );

            // Create audit log
            AuditLog::create([
                'target_type' => 'exeat_request',
                'target_id' => $exeatRequest->id,
                'staff_id' => $user->id,
                'student_id' => $exeatRequest->student_id,
                'action' => 'staff_comment',
                'details' => "Staff sent comment to student: {$validated['comment']}",
                'timestamp' => now()
            ]);

            return response()->json([
                'message' => 'Comment sent to student successfully',
                'notifications' => $notifications
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send comment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function rejectParentConsent(Request $request, $consentId)
    {
        $user = $request->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Check if staff has secretary role
        $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        if (!in_array('secretary', $roleNames)) {
            return response()->json([
                'message' => 'Unauthorized. Only Secretary can reject parent consents.'
            ], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
            'notify_parent' => 'boolean'
        ]);

        $consent = \App\Models\ParentConsent::with('exeatRequest.student')->find($consentId);

        if (!$consent) {
            return response()->json([
                'message' => 'Parent consent not found'
            ], 404);
        }

        if ($consent->consent_status !== 'pending') {
            return response()->json([
                'message' => 'This consent request has already been processed'
            ], 400);
        }

        if ($consent->exeatRequest->status !== 'parent_consent') {
            return response()->json([
                'message' => 'Exeat request is not in parent consent stage'
            ], 400);
        }

        // Check if consent has expired
        if ($consent->expires_at && now()->gt($consent->expires_at)) {
            return response()->json([
                'message' => 'This consent link has expired'
            ], 410);
        }

        DB::beginTransaction();
        try {
            // Update consent status
            $consent->update([
                'consent_status' => 'rejected',
                'acted_by_staff_id' => $user->id,
                'action_type' => 'secretary_rejection',
                'secretary_reason' => $request->reason,
                'consent_timestamp' => now()
            ]);

            // Move exeat request to rejected status
            $exeatRequest = $consent->exeatRequest;
            $exeatRequest->update(['status' => 'rejected']);

            // Create audit log
            AuditLog::create([
                'target_type' => 'exeat_request',
                'target_id' => $exeatRequest->id,
                'staff_id' => $user->id,
                'student_id' => $exeatRequest->student_id,
                'action' => 'secretary_parent_consent_rejection',
                'details' => "Secretary rejected parent consent on behalf of parent. Reason: {$request->reason}",
                'timestamp' => now()
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Rejection failed: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Parent consent rejected successfully on behalf of parent',
            'data' => [
                'consent' => $consent->fresh(),
                'exeat_request' => $exeatRequest->fresh()
            ]
        ]);
    }

    /**
     * Get statistics for Secretary parent consent actions.
     */
    public function getParentConsentStats()
    {
        $user = request()->user();
        if (!($user instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Check if staff has secretary role
        $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        if (!in_array('secretary', $roleNames)) {
            return response()->json([
                'message' => 'Unauthorized. Only Secretary can access parent consent statistics.'
            ], 403);
        }

        $totalPending = \App\Models\ParentConsent::where('consent_status', 'pending')
            ->whereHas('exeatRequest', function ($q) {
                $q->where('status', 'parent_consent');
            })
            ->count();

        $totalActedBySecretary = \App\Models\ParentConsent::where('acted_by_staff_id', $user->id)
            ->whereIn('action_type', ['secretary_approval', 'secretary_rejection'])
            ->count();

        $approvedBySecretary = \App\Models\ParentConsent::where('acted_by_staff_id', $user->id)
            ->where('action_type', 'secretary_approval')
            ->count();

        $rejectedBySecretary = \App\Models\ParentConsent::where('acted_by_staff_id', $user->id)
            ->where('action_type', 'secretary_rejection')
            ->count();

        return response()->json([
            'message' => 'Parent consent statistics retrieved successfully.',
            'data' => [
                'pending_consents' => $totalPending,
                'total_acted_by_secretary' => $totalActedBySecretary,
                'approved_by_secretary' => $approvedBySecretary,
                'rejected_by_secretary' => $rejectedBySecretary
            ]
        ]);
    }

    /**
     * Calculate days overdue using exact 24-hour periods at 11:59 PM
     *
     * @param \Carbon\Carbon $returnDate
     * @param \Carbon\Carbon $actualReturnTime
     * @return int
     */
    private function calculateDaysOverdue(\Carbon\Carbon $returnDate, \Carbon\Carbon $actualReturnTime): int
    {
        // Set return date to 11:59 PM of the expected return date
        $returnDateEnd = $returnDate->copy()->setTime(23, 59, 59);

        // If actual return is before or at 11:59 PM of return date, no debt
        if ($actualReturnTime->lte($returnDateEnd)) {
            return 0;
        }

        // Calculate full 24-hour periods after 11:59 PM of return date
        $daysPassed = 0;
        $currentCheckDate = $returnDateEnd->copy();

        while ($currentCheckDate->lt($actualReturnTime)) {
            $currentCheckDate->addDay()->setTime(23, 59, 59);
            $daysPassed++;
        }

        return $daysPassed;
    }
}
