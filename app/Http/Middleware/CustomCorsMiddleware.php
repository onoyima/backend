<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomCorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Handle preflight OPTIONS requests
        if ($request->getMethod() === "OPTIONS") {
            $response = response('', 200);
        } else {
            $response = $next($request);
        }

        // Get the origin from the request
        $origin = $request->header('Origin');
        
        // Define allowed origins
        $allowedOrigins = [
            'https://exeat.vercel.app',
            'https://attendance.veritas.edu.ng',
            'http://localhost:3000',
            'http://localhost:3001',
        ];

        // Check if origin is allowed or matches patterns
        $isAllowed = false;
        if (in_array($origin, $allowedOrigins)) {
            $isAllowed = true;
        } elseif ($origin && (
            preg_match('/^https:\/\/.*\.veritas\.edu\.ng$/', $origin) ||
            preg_match('/^https:\/\/.*\.vercel\.app$/', $origin)
        )) {
            $isAllowed = true;
        }

        // Set CORS headers
        if ($isAllowed) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }
        
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, X-XSRF-TOKEN');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }
}