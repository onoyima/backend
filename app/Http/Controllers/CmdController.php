<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExeatRequest;
use Illuminate\Support\Facades\Log;

class CmdController extends Controller
{
    // GET /api/cmd/exeat-requests?type=medical
    public function index(Request $request)
    {
        $type = $request->query('type', null);
        $query = ExeatRequest::query();
        if ($type) {
            $query->where('type', $type);
        }
        $exeats = $query->orderBy('created_at', 'desc')->get();
        Log::info('CMD viewed exeat requests', ['type' => $type, 'count' => $exeats->count()]);
        return response()->json(['exeat_requests' => $exeats]);
    }

    // POST /api/cmd/exeat-requests/{id}/approve
    public function approve(Request $request, $id)
    {
        $exeat = ExeatRequest::find($id);
        if (!$exeat) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'comment' => 'nullable|string',
        ]);
        $exeat->status = $validated['status'];
        $exeat->save();
        Log::info('CMD approved/rejected exeat request', ['exeat_id' => $id, 'status' => $validated['status']]);
        return response()->json(['message' => "Exeat request {$validated['status']}.", 'exeat_request' => $exeat]);
    }
}
