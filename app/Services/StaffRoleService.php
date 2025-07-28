<?php
namespace App\Services;

use App\Models\Staff;
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

        $staff->exeatRoles()->create([
            'exeat_role_id' => $exeatRoleId,
            'assigned_at' => now(),
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
        $role->delete();
    }
}
