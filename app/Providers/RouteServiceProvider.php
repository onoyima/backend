<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function () {
            $router = $this->app['router'];

            $router->middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Stateless routes without session middleware
            $router->group([], base_path('routes/stateless.php'));

            $router->middleware('web')
                ->group(base_path('routes/web.php'));

            // Register Sanctum's routes for CSRF cookie
            if (class_exists(\Laravel\Sanctum\Sanctum::class)) {
                // \Laravel\Sanctum\Sanctum::routes($router);
            }
        });
    }
}
