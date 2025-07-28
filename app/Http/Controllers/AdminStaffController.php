<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Staff;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminStaffController extends Controller
{
    // GET /api/admin/staff/assignments
    public function assignments()
    {
        $staff = Staff::with(['exeatRoles.role'])->get();
        $history = [];
        foreach ($staff as $s) {
            foreach ($s->exeatRoles as $assignment) {
                if ($assignment->role) {
                    $history[] = [
                        'staff_name' => trim($s->fname . ' ' . ($s->middle_name ?? '') . ' ' . $s->lname),
                        'staff_email' => $s->email,
                        'role_display_name' => $assignment->role->display_name,
                        'role_name' => $assignment->role->name,
                        'assigned_at' => $assignment->assigned_at,
                    ];
                }
            }
        }
        return response()->json(['history' => $history]);
    }
    // GET /api/admin/staff
    public function index()
    {
        $staff = Staff::all(['id', 'fname', 'lname', 'email', 'status']);
        return response()->json(['staff' => $staff]);
    }

    // POST /api/admin/staff
    public function store(Request $request)
    {
        $validated = $request->validate([
            'fname' => 'required|string',
            'lname' => 'required|string',
            'email' => 'required|email|unique:staff,email',
            'password' => 'required|string|min:6',
            'status' => 'nullable|string',
        ]);
        $staff = Staff::create([
            'fname' => $validated['fname'],
            'lname' => $validated['lname'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'] ?? 'active',
        ]);
        Log::info('Admin created staff', ['staff_id' => $staff->id]);
        return response()->json(['message' => 'Staff created.', 'staff' => $staff], 201);
    }

    // GET /api/admin/staff/{id}
    public function show($id)
    {
        $staff = Staff::with(['exeatRoles.role'])->find($id);
        if (!$staff) {
            return response()->json(['message' => 'Staff not found.'], 404);
        }
        // Map exeatRoles to exeat_roles for frontend compatibility
        $staffArr = $staff->toArray();
        $staffArr['exeat_roles'] = array_map(function($role) {
            return isset($role['exeat_role']) ? $role['exeat_role'] : null;
        }, $staffArr['exeat_roles'] ?? []);
        // Remove nulls
        $staffArr['exeat_roles'] = array_values(array_filter($staffArr['exeat_roles']));
        return response()->json(['staff' => $staffArr]);
    }

    // PUT /api/admin/staff/{id}
    public function update(Request $request, $id)
    {
        $staff = Staff::find($id);
        if (!$staff) {
            return response()->json(['message' => 'Staff not found.'], 404);
        }
        $validated = $request->validate([
            'fname' => 'sometimes|required|string',
            'lname' => 'sometimes|required|string',
            'email' => 'sometimes|required|email|unique:staff,email,' . $id,
            'password' => 'nullable|string|min:6',
            'status' => 'nullable|string',
        ]);
        $staff->fill($validated);
        if (!empty($validated['password'])) {
            $staff->password = Hash::make($validated['password']);
        }
        $staff->save();
        Log::info('Admin updated staff', ['staff_id' => $staff->id]);
        return response()->json(['message' => 'Staff updated.', 'staff' => $staff]);
    }

    // DELETE /api/admin/staff/{id}
    public function destroy($id)
    {
        $staff = Staff::find($id);
        if (!$staff) {
            return response()->json(['message' => 'Staff not found.'], 404);
        }
        $staff->delete();
        Log::info('Admin deleted staff', ['staff_id' => $id]);
        return response()->json(['message' => 'Staff deleted.']);
    }

    // POST /api/admin/staff/{id}/assign-exeat-role
    public function assignExeatRole(Request $request, $id)
    {
        $staff = Staff::find($id);
        if (!$staff) {
            return response()->json(['message' => 'Staff not found.'], 404);
        }
        $validated = $request->validate([
            'exeat_role_id' => 'required|exists:exeat_roles,id',
        ]);
        // Prevent duplicate assignment
        $existing = $staff->exeatRoles()->where('exeat_role_id', $validated['exeat_role_id'])->first();
        if ($existing) {
            return response()->json(['message' => 'Staff already has this exeat role.'], 409);
        }
        $staff->exeatRoles()->create([
            'exeat_role_id' => $validated['exeat_role_id'],
            'assigned_at' => now(),
        ]);
        return response()->json(['message' => 'Exeat role assigned to staff.']);
    }

    // DELETE /api/admin/staff/{id}/unassign-exeat-role
    public function unassignExeatRole(Request $request, $id)
    {
        $staff = Staff::find($id);
        if (!$staff) {
            return response()->json(['message' => 'Staff not found.'], 404);
        }
        $validated = $request->validate([
            'exeat_role_id' => 'required|exists:exeat_roles,id',
        ]);
        $role = $staff->exeatRoles()->where('exeat_role_id', $validated['exeat_role_id'])->first();
        if (!$role) {
            return response()->json(['message' => 'Exeat role not assigned to staff.'], 404);
        }
        $role->delete();
        return response()->json(['message' => 'Exeat role unassigned from staff.']);
    }
}
