<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StaffExeatRole;
use App\Models\StudentRoleUser;
use Illuminate\Support\Facades\Log;

class LookupController extends Controller
{
    // GET /api/lookups/roles
    public function roles()
    {
        // Get unique staff exeat roles
        $staffRoles = StaffExeatRole::distinct()->pluck('exeat_role')->toArray();
        // Get unique student role IDs (for demo, just IDs; in real app, join to role names)
        $studentRoleIds = StudentRoleUser::distinct()->pluck('student_role_id')->toArray();
        Log::info('Roles lookup requested');
        return response()->json([
            'staff_roles' => $staffRoles,
            'student_role_ids' => $studentRoleIds,
        ]);
    }

    public function hostelAdmins(Request $request)
    {
        // Add pagination with configurable per_page parameter
        $perPage = $request->get('per_page', 20); // Default 20 items per page
        $perPage = min($perPage, 100); // Maximum 100 items per page
        
        // Get all staff IDs with the hostel_admin role
        $hostelAdminIds = StaffExeatRole::where('exeat_role', 'hostel_admin')->pluck('staff_id');
        // Get staff details with pagination
        $admins = \App\Models\Staff::whereIn('id', $hostelAdminIds)
            ->select(['id', 'fname', 'lname', 'email'])
            ->paginate($perPage);
            
        Log::info('Hostel admins lookup requested');
        
        return response()->json([
            'hostel_admins' => $admins->items(),
            'pagination' => [
                'current_page' => $admins->currentPage(),
                'last_page' => $admins->lastPage(),
                'per_page' => $admins->perPage(),
                'total' => $admins->total(),
                'from' => $admins->firstItem(),
                'to' => $admins->lastItem(),
                'has_more_pages' => $admins->hasMorePages()
            ]
        ]);
    }

    public function hostels(Request $request)
    {
        // Add pagination with configurable per_page parameter
        $perPage = $request->get('per_page', 20); // Default 20 items per page
        $perPage = min($perPage, 100); // Maximum 100 items per page
        
        $hostels = \App\Models\VunaAccomodation::select(['id', 'name'])->paginate($perPage);
        
        Log::info('Hostels lookup requested');
        
        return response()->json([
            'hostels' => $hostels->items(),
            'pagination' => [
                'current_page' => $hostels->currentPage(),
                'last_page' => $hostels->lastPage(),
                'per_page' => $hostels->perPage(),
                'total' => $hostels->total(),
                'from' => $hostels->firstItem(),
                'to' => $hostels->lastItem(),
                'has_more_pages' => $hostels->hasMorePages()
            ]
        ]);
    }

    public function exeatUsage()
    {
        $total = \App\Models\ExeatRequest::count();
        $approved = \App\Models\ExeatRequest::where('status', 'approved')->count();
        $rejected = \App\Models\ExeatRequest::where('status', 'rejected')->count();
        $pending = \App\Models\ExeatRequest::where('status', 'pending')->count();
        $byCategory = \App\Models\ExeatRequest::selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category');
        \Log::info('Exeat usage analytics requested');
        return response()->json([
            'total' => $total,
            'approved' => $approved,
            'rejected' => $rejected,
            'pending' => $pending,
            'by_category' => $byCategory,
        ]);
    }

    public function auditLogs(Request $request)
    {
        // Add pagination with configurable per_page parameter
        $perPage = $request->get('per_page', 50); // Default 50 items per page for logs
        $perPage = min($perPage, 200); // Maximum 200 items per page for logs
        
        $logs = \App\Models\AuditLog::orderBy('created_at', 'desc')
            ->select(['id', 'user_id', 'action', 'created_at'])
            ->paginate($perPage);
            
        \Log::info('Audit logs lookup requested');
        
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
}
