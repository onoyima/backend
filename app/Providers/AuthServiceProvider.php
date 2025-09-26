<?php

namespace App\Providers;

use App\Models\Staff;
use App\Models\Student;
use App\Policies\DashboardPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Staff::class => DashboardPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define gates that work with both Staff and Student models
        Gate::define('viewAdminDashboard', function ($user) {
            if ($user instanceof Staff) {
                $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
                return in_array('admin', $roleNames) || in_array('super_admin', $roleNames);
            }
            return false;
        });

        Gate::define('viewDeanDashboard', function ($user) {
            if ($user instanceof Staff) {
                $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
                return in_array('dean', $roleNames) || in_array('admin', $roleNames) || in_array('super_admin', $roleNames);
            }
            return false;
        });

        Gate::define('viewStaffDashboard', function ($user) {
            if ($user instanceof Staff) {
                $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
                $allowedRoles = ['staff', 'teacher', 'housemaster', 'security', 'dean', 'admin', 'super_admin', 'cmd', 'secretary', 'hostel_admin'];
                return !empty(array_intersect($roleNames, $allowedRoles));
            }
            return false;
        });

        Gate::define('viewSecurityDashboard', function ($user) {
            if ($user instanceof Staff) {
                $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
                return in_array('security', $roleNames) || in_array('admin', $roleNames) || in_array('super_admin', $roleNames);
            }
            return false;
        });

        Gate::define('viewHousemasterDashboard', function ($user) {
            if ($user instanceof Staff) {
                $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
                return in_array('housemaster', $roleNames) || in_array('admin', $roleNames) || in_array('super_admin', $roleNames);
            }
            return false;
        });

        Gate::define('viewDashboardWidgets', function ($user) {
            if ($user instanceof Staff) {
                $roleNames = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
                $allowedRoles = ['staff', 'teacher', 'housemaster', 'security', 'dean', 'admin', 'super_admin', 'cmd', 'secretary', 'hostel_admin'];
                return !empty(array_intersect($roleNames, $allowedRoles));
            } elseif ($user instanceof Student) {
                return true; // Students can view basic dashboard widgets
            }
            return false;
        });
    }
}