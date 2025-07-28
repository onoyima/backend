<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventSessionForApi
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Disable session for API routes
        if ($request->is('api/*')) {
            // Force array session driver
            config(['session.driver' => 'array']);
            
            // Clear any existing session
            $request->setLaravelSession(null);
            
            // Disable session middleware for this request
            $request->attributes->set('session.disabled', true);
            
            // Remove any session cookies from the request
            $request->cookies->remove('laravel_session');
        }

        return $next($request);
    }
}
