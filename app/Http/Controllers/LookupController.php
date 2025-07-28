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

    public function hostelAdmins()
    {
        // Get all staff IDs with the hostel_admin role
        $hostelAdminIds = StaffExeatRole::where('exeat_role', 'hostel_admin')->pluck('staff_id');
        // Get staff details
        $admins = \App\Models\Staff::whereIn('id', $hostelAdminIds)
            ->get(['id', 'fname', 'lname', 'email']);
        Log::info('Hostel admins lookup requested');
        return response()->json(['hostel_admins' => $admins]);
    }

    public function hostels()
    {
        $hostels = \App\Models\VunaAccomodation::all(['id', 'name']);
        Log::info('Hostels lookup requested');
        return response()->json(['hostels' => $hostels]);
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

    public function auditLogs()
    {
        $logs = \App\Models\AuditLog::orderBy('created_at', 'desc')->get(['id', 'user_id', 'action', 'created_at']);
        \Log::info('Audit logs lookup requested');
        return response()->json(['audit_logs' => $logs]);
    }
}
