<?php

namespace App\Policies;

use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

class DashboardPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view admin dashboard.
     */
    public function viewAdminDashboard(Staff $staff): bool
    {
        $roleNames = $staff->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        return in_array('admin', $roleNames) || in_array('super_admin', $roleNames);
    }

    /**
     * Determine whether the user can view dean dashboard.
     */
    public function viewDeanDashboard(Staff $staff): bool
    {
        $roleNames = $staff->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        return in_array('dean', $roleNames) || in_array('admin', $roleNames) || in_array('super_admin', $roleNames);
    }

    /**
     * Determine whether the user can view staff dashboard.
     */
    public function viewStaffDashboard(Staff $staff): bool
    {
        $roleNames = $staff->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        $allowedRoles = ['staff', 'teacher', 'housemaster', 'security', 'dean', 'admin', 'super_admin', 'cmd', 'deputy_dean', 'hostel_admin'];
        return !empty(array_intersect($roleNames, $allowedRoles));
    }

    /**
     * Determine whether the user can view security dashboard.
     */
    public function viewSecurityDashboard(Staff $staff): bool
    {
        $roleNames = $staff->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        return in_array('security', $roleNames) || in_array('admin', $roleNames) || in_array('super_admin', $roleNames);
    }

    /**
     * Determine whether the user can view housemaster dashboard.
     */
    public function viewHousemasterDashboard(Staff $staff): bool
    {
        $roleNames = $staff->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
        return in_array('housemaster', $roleNames) || in_array('admin', $roleNames) || in_array('super_admin', $roleNames);
    }

    /**
     * Determine whether the user can view teacher dashboard.
     */
    public function viewTeacherDashboard(User $user): bool
    {
        return $user->hasRole('teacher') || $user->hasRole('admin') || $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can access dashboard widgets.
     */
    public function viewDashboardWidgets(User $user): bool
    {
        return $user->hasAnyRole([
            'staff', 'teacher', 'housemaster', 'security', 
            'dean', 'admin', 'super_admin', 'student'
        ]);
    }
}