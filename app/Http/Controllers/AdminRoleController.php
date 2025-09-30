<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StaffExeatRole;
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
        $role->exeat_role_id = $validated['exeat_role_id'];
        $role->save();
        
        // Refresh staff data with updated roles to prevent frontend caching issues
        $staff = \App\Models\Staff::with(['exeatRoles.role', 'assignedRoles.role', 'positions'])->find($role->staff_id);
        
        Log::info('Admin updated role', ['role_id' => $role->id]);
        return response()->json([
            'message' => 'Role updated.',
            'role' => $role,
            'staff' => $staff,
            'updated_at' => now()->toISOString()
        ]);
    }

    // DELETE /api/admin/roles/{id}
    public function destroy($id)
    {
        $role = StaffExeatRole::find($id);
        if (!$role) {
            return response()->json(['message' => 'Role not found.'], 404);
        }
        
        // Get staff ID before deleting the role
        $staffId = $role->staff_id;
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
