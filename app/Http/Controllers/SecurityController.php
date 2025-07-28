<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SecuritySignout;
use App\Models\ExeatRequest;
use Illuminate\Support\Facades\Log;

class SecurityController extends Controller
{
    // POST /api/security/validate
    public function validateStudent(Request $request)
    {
        $validated = $request->validate([
            'qr_code' => 'nullable|string',
            'matric_number' => 'nullable|string',
        ]);
        // For demo: find by QR or matric
        $exeat = null;
        if (!empty($validated['qr_code'])) {
            $exeat = ExeatRequest::whereRaw('CONCAT("QR-", id, "-", student_id) = ?', [$validated['qr_code']])->first();
        } elseif (!empty($validated['matric_number'])) {
            $exeat = ExeatRequest::whereHas('student', function($q) use ($validated) {
                $q->where('matric_number', $validated['matric_number']);
            })->where('status', 'approved')->first();
        }
        if (!$exeat) {
            return response()->json(['message' => 'No valid exeat found.'], 404);
        }
        Log::info('Security validated student', ['exeat_id' => $exeat->id]);
        return response()->json(['message' => 'Student validated.', 'exeat_request' => $exeat]);
    }

    // POST /api/security/signout/{exeat_request_id}
    public function signOut(Request $request, $exeat_request_id)
    {
        $exeat = ExeatRequest::find($exeat_request_id);
        if (!$exeat) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }
        $signout = SecuritySignout::create([
            'exeat_request_id' => $exeat->id,
            'signed_out_at' => now(),
            'signed_in_at' => null,
            'security_id' => $request->user()->id,
        ]);
        Log::info('Security signed out student at gate', ['exeat_id' => $exeat->id, 'security_id' => $request->user()->id]);
        return response()->json(['message' => 'Student signed out at gate.', 'signout' => $signout]);
    }

    // POST /api/security/signin/{exeat_request_id}
    public function signIn(Request $request, $exeat_request_id)
    {
        $signout = SecuritySignout::where('exeat_request_id', $exeat_request_id)
            ->whereNull('signed_in_at')
            ->first();
        if (!$signout) {
            return response()->json(['message' => 'No active security signout found.'], 404);
        }
        $signout->signed_in_at = now();
        $signout->save();
        Log::info('Security signed in student at gate', ['exeat_id' => $exeat_request_id, 'security_id' => $request->user()->id]);
        return response()->json(['message' => 'Student signed in at gate.', 'signin' => $signout]);
    }
}
