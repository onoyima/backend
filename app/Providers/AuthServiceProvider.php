<?php

namespace App\Providers;

use App\Models\User;
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
        User::class => DashboardPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define additional gates if needed
        Gate::define('viewAdminDashboard', [DashboardPolicy::class, 'viewAdminDashboard']);
        Gate::define('viewDeanDashboard', [DashboardPolicy::class, 'viewDeanDashboard']);
        Gate::define('viewStaffDashboard', [DashboardPolicy::class, 'viewStaffDashboard']);
        Gate::define('viewSecurityDashboard', [DashboardPolicy::class, 'viewSecurityDashboard']);
        Gate::define('viewHousemasterDashboard', [DashboardPolicy::class, 'viewHousemasterDashboard']);
        Gate::define('viewDashboardWidgets', [DashboardPolicy::class, 'viewDashboardWidgets']);
    }
}