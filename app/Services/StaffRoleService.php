<?php
namespace App\Services;

use App\Models\Staff;
use App\Models\AuditLog;
use App\Models\ExeatRole;
use Illuminate\Validation\ValidationException;

class StaffRoleService
{
    public function assignRole(Staff $staff, int $exeatRoleId): void
    {
        $alreadyAssigned = $staff->exeatRoles()->where('exeat_role_id', $exeatRoleId)->exists();
        if ($alreadyAssigned) {
            throw ValidationException::withMessages([
                'exeat_role_id' => ['Staff already has this exeat role.']
            ]);
        }

        $roleAssignment = $staff->exeatRoles()->create([
            'exeat_role_id' => $exeatRoleId,
            'assigned_at' => now(),
        ]);
        
        // Get role information for audit log
        $role = ExeatRole::find($exeatRoleId);
        
        // Create audit log for role assignment (system-generated)
        AuditLog::create([
            'staff_id' => null, // System-generated assignment
            'student_id' => null,
            'action' => 'role_assigned',
            'target_type' => 'staff_role',
            'target_id' => $roleAssignment->id,
            'details' => json_encode([
                'assigned_staff_id' => $staff->id,
                'assigned_staff_name' => $staff->fname . ' ' . $staff->lname,
                'role_id' => $exeatRoleId,
                'role_name' => $role->name ?? 'Unknown',
                'role_display_name' => $role->display_name ?? 'Unknown',
                'assigned_by' => 'System',
                'assigned_at' => $roleAssignment->assigned_at
            ])
        ]);
    }

    public function unassignRole(Staff $staff, int $exeatRoleId): void
    {
        $role = $staff->exeatRoles()->where('exeat_role_id', $exeatRoleId)->first();
        if (!$role) {
            throw ValidationException::withMessages([
                'exeat_role_id' => ['Staff does not have this role.']
            ]);
        }
        
        // Store role info for audit log before deletion
        $roleInfo = $role->load('role');
        
        // Create audit log for role unassignment (system-generated)
        AuditLog::create([
            'staff_id' => null, // System-generated unassignment
            'student_id' => null,
            'action' => 'role_unassigned',
            'target_type' => 'staff_role',
            'target_id' => $role->id,
            'details' => json_encode([
                'assigned_staff_id' => $staff->id,
                'assigned_staff_name' => $staff->fname . ' ' . $staff->lname,
                'role_id' => $exeatRoleId,
                'role_name' => $roleInfo->role->name ?? 'Unknown',
                'role_display_name' => $roleInfo->role->display_name ?? 'Unknown',
                'removed_by' => 'System',
                'assigned_at' => $role->assigned_at
            ])
        ]);
        
        $role->delete();
    }
}
