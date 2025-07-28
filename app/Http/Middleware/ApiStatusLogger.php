<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ApiStatusLogger
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        \Log::info('ApiStatusLogger: Middleware triggered', ['path' => $request->path()]);
        $response = $next($request);

        // Only log API requests
        if (strpos($request->path(), 'api/') === 0) {
            $endpoint = '/' . $request->path();
            $statusCode = $response->getStatusCode();
            $result = $statusCode . ' ' . ($response->exception ? $response->exception->getMessage() : $response->getContent());
            // Try to get the controller file name from the route action
            $route = $request->route();
            $controllerFile = null;
            if ($route && method_exists($route, 'getActionName')) {
                $action = $route->getActionName();
                if (strpos($action, '@') !== false) {
                    $controllerClass = explode('@', $action)[0];
                    try {
                        $reflector = new \ReflectionClass($controllerClass);
                        $controllerFile = basename($reflector->getFileName());
                    } catch (\Exception $e) {
                        $controllerFile = null;
                    }
                }
            }
            $entry = [
                'endpoint' => $endpoint,
                'result' => $result,
                'file' => $controllerFile,
                'timestamp' => now()->toDateTimeString(),
            ];
            // Retrieve the current list from cache
            $list = Cache::get('api_status_list', []);
            // Clear if older than 5 minutes
            if (!empty($list) && isset($list[0]['timestamp'])) {
                $firstTime = \Carbon\Carbon::parse($list[0]['timestamp']);
                if ($firstTime->diffInMinutes(now()) >= 5) {
                    $list = [];
                }
            }
            // Add new entry to the front
            array_unshift($list, $entry);
            // Limit to last 20 requests
            $list = array_slice($list, 0, 20);
            Cache::put('api_status_list', $list, 3600); // 1 hour expiry
            \Log::info('ApiStatusLogger: Cache write complete', $entry);
        }
        return $response;
    }
}
