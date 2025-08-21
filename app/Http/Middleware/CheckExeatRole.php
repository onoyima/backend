<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckExeatRole
{
    public function handle(Request $request, Closure $next, $role)
    {
        $user = $request->user();
        if ($user && $user instanceof \App\Models\Staff) {
            // Hardcoded super admin override
            if ($user->id == 596 || $user->id == 2) {
                return $next($request);
            }
            
            $roles = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
            
            // Admin users can access any role
            if (in_array('admin', $roles)) {
                return $next($request);
            }
            
            // Check if user has the specific required role
            if (in_array($role, $roles)) {
                return $next($request);
            }
        }
        return response()->json(['error' => 'Unauthorized. Required role: ' . $role], 403);
    }
}
