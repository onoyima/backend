<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\ExeatRequest;
use Illuminate\Support\Facades\Storage;

class StudentExeatRequestController extends Controller
{
    // POST /api/student/exeat-requests
    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'type' => 'required|string',
            'category' => 'required|string',
            'reason' => 'required|string',
            'departure_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:departure_date',
            'contact_method' => 'required|string',
            'location' => 'nullable|string',
        ]);
        $exeat = ExeatRequest::create([
            'student_id' => $user->id,
            'type' => $validated['type'],
            'category' => $validated['category'],
            'reason' => $validated['reason'],
            'departure_date' => $validated['departure_date'],
            'end_date' => $validated['end_date'],
            'contact_method' => $validated['contact_method'],
            'location' => $validated['location'] ?? null,
            'status' => 'pending',
        ]);
        Log::info('Student created exeat request', ['student_id' => $user->id, 'exeat_id' => $exeat->id]);
        return response()->json(['message' => 'Exeat request created successfully.', 'exeat_request' => $exeat], 201);
    }

    // GET /api/student/exeat-requests
    public function index(Request $request)
    {
        $user = $request->user();
        $exeats = ExeatRequest::where('student_id', $user->id)->orderBy('created_at', 'desc')->get();
        return response()->json(['exeat_requests' => $exeats]);
    }

    // GET /api/student/exeat-requests/{id}
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $exeat = ExeatRequest::where('id', $id)->where('student_id', $user->id)->first();
        if (!$exeat) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }
        return response()->json(['exeat_request' => $exeat]);
    }

    // POST /api/student/exeat-requests/{id}/appeal
    public function appeal(Request $request, $id)
    {
        $user = $request->user();
        $exeat = ExeatRequest::where('id', $id)->where('student_id', $user->id)->first();
        if (!$exeat) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }
        $validated = $request->validate([
            'appeal_reason' => 'required|string',
        ]);
        $exeat->appeal_reason = $validated['appeal_reason'];
        $exeat->status = 'appeal';
        $exeat->save();
        Log::info('Student appealed exeat request', ['student_id' => $user->id, 'exeat_id' => $exeat->id]);
        return response()->json(['message' => 'Appeal submitted successfully.', 'exeat_request' => $exeat]);
    }

    // GET /api/student/exeat-requests/{id}/download
    public function download(Request $request, $id)
    {
        $user = $request->user();
        $exeat = ExeatRequest::where('id', $id)->where('student_id', $user->id)->first();
        if (!$exeat) {
            return response()->json(['message' => 'Exeat request not found.'], 404);
        }
        if ($exeat->status !== 'approved') {
            return response()->json(['message' => 'Exeat request is not approved yet.'], 403);
        }
        // For demo: return a JSON with a fake QR code string (in real app, generate PDF/QR)
        $qrCode = 'QR-' . $exeat->id . '-' . $exeat->student_id;
        return response()->json([
            'exeat_request' => $exeat,
            'qr_code' => $qrCode,
            'download_url' => null // Implement PDF/QR download as needed
        ]);
    }
}
