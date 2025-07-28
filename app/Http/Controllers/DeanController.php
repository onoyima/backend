<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExeatRequest;
use Illuminate\Support\Facades\Log;

class DeanController extends Controller
{
    // GET /api/dean/exeat-requests
    public function index(Request $request)
    {
        // For demo: return all approved/verified exeat requests
        $exeats = ExeatRequest::where('status', 'approved')->orderBy('created_at', 'desc')->get();
        Log::info('Dean viewed all approved exeat requests', ['count' => $exeats->count()]);
        return response()->json(['exeat_requests' => $exeats]);
    }

    // POST /api/dean/exeat-requests/bulk-approve
    public function bulkApprove(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);
        $count = ExeatRequest::whereIn('id', $validated['ids'])
            ->update(['status' => 'approved']);
        Log::info('Dean bulk approved exeat requests', ['ids' => $validated['ids']]);
        return response()->json(['message' => "$count exeat requests approved."]);
    }
}
