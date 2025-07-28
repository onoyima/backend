<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminConfigController extends Controller
{
    // GET /api/admin/config
    public function show(Request $request)
    {
        // For demo, return static config
        $config = [
            'maintenance_mode' => false,
            'email_from' => 'noreply@veritas.edu.ng',
        ];
        return response()->json(['config' => $config]);
    }

    // PUT /api/admin/config
    public function update(Request $request)
    {
        $validated = $request->validate([
            'maintenance_mode' => 'boolean',
            'email_from' => 'string|email',
        ]);
        // For demo, just log
        Log::info('Admin updated config', $validated);
        // In real app, save config to DB or .env
        return response()->json(['message' => 'Config updated.', 'config' => $validated]);
    }
}
