<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Sanctum\PersonalAccessToken;

class RevokeIdleTokens
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated via Sanctum
        if ($request->user() && $request->bearerToken()) {
            $token = $request->user()->currentAccessToken();
            
            if ($token) {
                // Check if token has been idle for more than 30 minutes
                $idleTime = now()->diffInMinutes($token->last_used_at ?? $token->created_at);
                
                if ($idleTime > 30) {
                    // Revoke the token
                    $token->delete();
                    
                    return response()->json([
                        'message' => 'Token has been revoked due to inactivity.',
                        'error' => 'token_expired'
                    ], 401);
                }
                
                // Update last_used_at timestamp
                $token->forceFill(['last_used_at' => now()])->save();
            }
        }

        return $next($request);
    }
}
