<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StaffExeatRole;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

class AdminRoleController extends Controller
{
    // GET /api/admin/roles
    public function index()
    {
        $roles = \App\Models\ExeatRole::all();
        return response()->json(['roles' => $roles]);
    }

    // POST /api/admin/roles
    public function store(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|integer',
            'exeat_role_id' => 'required|exists:exeat_roles,id',
        ]);
        $role = \App\Models\StaffExeatRole::create([
            'staff_id' => $validated['staff_id'],
            'exeat_role_id' => $validated['exeat_role_id'],
            'assigned_at' => now(),
        ]);
        
        // Refresh staff data with updated roles to prevent frontend caching issues
        $staff = \App\Models\Staff::with(['exeatRoles.role', 'assignedRoles.role', 'positions'])->find($validated['staff_id']);
        
        // Create audit log for role assignment
        AuditLog::create([
            'staff_id' => $request->user()->id,
            'student_id' => null,
            'action' => 'role_assigned',
            'target_type' => 'staff_role',
            'target_id' => $role->id,
            'details' => json_encode([
                'assigned_staff_id' => $validated['staff_id'],
                'assigned_staff_name' => $staff->fname . ' ' . $staff->lname,
                'role_id' => $validated['exeat_role_id'],
                'role_name' => $role->role->name ?? 'Unknown',
                'role_display_name' => $role->role->display_name ?? 'Unknown',
                'assigned_by' => $request->user()->fname . ' ' . $request->user()->lname,
                'assigned_at' => $role->assigned_at
            ])
        ]);
        
        Log::info('Admin created role', ['role_id' => $role->id]);
        return response()->json([
            'message' => 'Role assigned.',
            'role' => $role,
            'staff' => $staff,
            'updated_at' => now()->toISOString()
        ], 201);
    }

    // PUT /api/admin/roles/{id}
    public function update(Request $request, $id)
    {
        $role = \App\Models\StaffExeatRole::find($id);
        if (!$role) {
            return response()->json(['message' => 'Role not found.'], 404);
        }
        $validated = $request->validate([
            'exeat_role_id' => 'required|exists:exeat_roles,id',
        ]);
        
        // Store old role info for audit log
        $oldRoleId = $role->exeat_role_id;
        $oldRole = $role->role;
        
        $role->exeat_role_id = $validated['exeat_role_id'];
        $role->save();
        
        // Refresh staff data with updated roles to prevent frontend caching issues
        $staff = \App\Models\Staff::with(['exeatRoles.role', 'assignedRoles.role', 'positions'])->find($role->staff_id);
        
        // Create audit log for role assignment update
        AuditLog::create([
            'staff_id' => $request->user()->id,
            'student_id' => null,
            'action' => 'role_updated',
            'target_type' => 'staff_role',
            'target_id' => $role->id,
            'details' => json_encode([
                'assigned_staff_id' => $role->staff_id,
                'assigned_staff_name' => $staff->fname . ' ' . $staff->lname,
                'old_role_id' => $oldRoleId,
                'old_role_name' => $oldRole->name ?? 'Unknown',
                'old_role_display_name' => $oldRole->display_name ?? 'Unknown',
                'new_role_id' => $validated['exeat_role_id'],
                'new_role_name' => $role->role->name ?? 'Unknown',
                'new_role_display_name' => $role->role->display_name ?? 'Unknown',
                'updated_by' => $request->user()->fname . ' ' . $request->user()->lname
            ])
        ]);
        
        Log::info('Admin updated role', ['role_id' => $role->id]);
        return response()->json([
            'message' => 'Role updated.',
            'role' => $role,
            'staff' => $staff,
            'updated_at' => now()->toISOString()
        ]);
    }

    // DELETE /api/admin/roles/{id}
    public function destroy(Request $request, $id)
    {
        $role = StaffExeatRole::find($id);
        if (!$role) {
            return response()->json(['message' => 'Role not found.'], 404);
        }
        
        // Store role info for audit log before deletion
        $staffId = $role->staff_id;
        $roleInfo = $role->load('role', 'staff');
        
        // Create audit log for role assignment removal
        AuditLog::create([
            'staff_id' => $request->user()->id,
            'student_id' => null,
            'action' => 'role_unassigned',
            'target_type' => 'staff_role',
            'target_id' => $role->id,
            'details' => json_encode([
                'assigned_staff_id' => $staffId,
                'assigned_staff_name' => $roleInfo->staff->fname . ' ' . $roleInfo->staff->lname,
                'role_id' => $role->exeat_role_id,
                'role_name' => $roleInfo->role->name ?? 'Unknown',
                'role_display_name' => $roleInfo->role->display_name ?? 'Unknown',
                'removed_by' => $request->user()->fname . ' ' . $request->user()->lname,
                'assigned_at' => $role->assigned_at
            ])
        ]);
        
        $role->delete();
        
        // Refresh staff data with updated roles to prevent frontend caching issues
        $staff = \App\Models\Staff::with(['exeatRoles.role', 'assignedRoles.role', 'positions'])->find($staffId);
        
        Log::info('Admin deleted role', ['role_id' => $id]);
        return response()->json([
            'message' => 'Role deleted.',
            'staff' => $staff,
            'updated_at' => now()->toISOString()
        ]);
    }
}
