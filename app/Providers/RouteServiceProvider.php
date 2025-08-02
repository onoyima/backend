<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Define the 'api' rate limiter
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                optional($request->user())->id ?: $request->ip()
            );
        });

        // Register route groups
        $this->app->booted(function () {
            $router = $this->app['router'];

            // API routes with 'api' middleware
            $router->middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Stateless routes (no middleware)
            $router->group([], base_path('routes/stateless.php'));

            // Web routes with 'web' middleware
            $router->middleware('web')
                ->group(base_path('routes/web.php'));

            // Uncomment if using Sanctum for SPA authentication
            /*
            if (class_exists(\Laravel\Sanctum\Sanctum::class)) {
                \Laravel\Sanctum\Sanctum::routes($router);
            }
            */
        });
    }
}
