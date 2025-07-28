<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HostelSignout;
use App\Models\ExeatRequest;
use Illuminate\Support\Facades\Log;

class HostelController extends Controller
{
    // POST /api/hostel/signout/{exeat_request_id}
    public function signOut(Request $request, $exeat_request_id)
    {
        $exeat = ExeatRequest::find($exeat_request_id);
        if (!$exeat) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }
        $signout = HostelSignout::create([
            'exeat_request_id' => $exeat->id,
            'signed_out_at' => now(),
            'signed_in_at' => null,
            'hostel_admin_id' => $request->user()->id,
        ]);
        Log::info('Hostel admin signed out student', ['exeat_id' => $exeat->id, 'admin_id' => $request->user()->id]);
        return response()->json(['message' => 'Student signed out of hostel.', 'signout' => $signout]);
    }

    // POST /api/hostel/signin/{exeat_request_id}
    public function signIn(Request $request, $exeat_request_id)
    {
        $signout = HostelSignout::where('exeat_request_id', $exeat_request_id)
            ->whereNull('signed_in_at')
            ->first();
        if (!$signout) {
            return response()->json(['message' => 'No active hostel signout found.'], 404);
        }
        $signout->signed_in_at = now();
        $signout->save();
        Log::info('Hostel admin signed in student', ['exeat_id' => $exeat_request_id, 'admin_id' => $request->user()->id]);
        return response()->json(['message' => 'Student signed in to hostel.', 'signout' => $signout]);
    }

    // GET /api/hostels
    public function index(Request $request)
    {
        $hostels = \App\Models\VunaAccomodation::all(['id', 'name']);
        return response()->json(['hostels' => $hostels]);
    }

    // POST /api/hostels/{id}/assign-admin
    public function assignAdmin(Request $request, $id)
    {
        $validated = $request->validate([
            'staff_id' => 'required|integer|exists:staff,id',
        ]);
        // Create or update hostel_admin_assignments
        $assignment = \App\Models\HostelAdminAssignment::updateOrCreate(
            [
                'vuna_accomodation_id' => $id,
            ],
            [
                'staff_id' => $validated['staff_id'],
                'assigned_at' => now(),
            ]
        );
        \Log::info('Hostel admin assigned', ['hostel_id' => $id, 'staff_id' => $validated['staff_id']]);
        return response()->json(['message' => 'Hostel admin assigned.', 'assignment' => $assignment]);
    }
}
