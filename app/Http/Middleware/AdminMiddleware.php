<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check if user is a staff member with admin role
        if ($user->user_type === 'staff') {
            $staff = $user->staff;
            if ($staff && $staff->exeatRoles()->where('name', 'admin')->exists()) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Forbidden - Admin access required'], 403);
    }
}