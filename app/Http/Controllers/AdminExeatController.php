<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExeatRequest;
use Illuminate\Support\Facades\Log;

class AdminExeatController extends Controller
{
    // POST /api/admin/exeats/{id}/revoke
    public function revoke(Request $request, $id)
    {
        $exeat = ExeatRequest::find($id);
        if (!$exeat) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }
        $exeat->status = 'revoked';
        $exeat->save();
        Log::warning('Admin revoked exeat', ['exeat_id' => $id, 'admin_id' => $request->user()->id]);
        return response()->json(['message' => 'Exeat revoked.', 'exeat_request' => $exeat]);
    }
}
