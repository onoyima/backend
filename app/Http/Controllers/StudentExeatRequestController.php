<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\ExeatRequest;
use Illuminate\Support\Facades\Storage;
use App\Models\ExeatCategory;
use App\Models\StudentAcademic;
use App\Models\StudentContact;
use App\Models\VunaAccomodationHistory;
use App\Models\VunaAccomodation;

class StudentExeatRequestController extends Controller
{
    // POST /api/student/exeat-requests
    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'category_id' => 'required|integer|exists:exeat_categories,id',
            'reason' => 'required|string',
            'destination' => 'required|string',
            'departure_date' => 'required|date',
            'return_date' => 'required|date|after_or_equal:departure_date',
            'preferred_mode_of_contact' => 'required|in:whatsapp,text,phone_call,any',
        ]);
        // Get student academic info for matric_no
        $studentAcademic = StudentAcademic::where('student_id', $user->id)->first();
        // Get parent/guardian contact info
        $studentContact = StudentContact::where('student_id', $user->id)->first();
        // Get latest accommodation info
        $accommodationHistory = VunaAccomodationHistory::where('student_id', $user->id)->orderBy('created_at', 'desc')->first();
        $accommodation = null;
        if ($accommodationHistory) {
            $accommodationModel = VunaAccomodation::find($accommodationHistory->vuna_accomodation_id);
            $accommodation = $accommodationModel ? $accommodationModel->name : null;
        }
        // Prevent new request if previous is not completed
        $existing = ExeatRequest::where('student_id', $user->id)->where('status', '!=', 'completed')->first();
        if ($existing) {
            return response()->json(['message' => 'You cannot submit a new exeat request until your previous one is completed.'], 403);
        }
        // Get category
        $category = ExeatCategory::find($validated['category_id']);
        $isMedical = strtolower($category->name) === 'medical';
        $initialStatus = $isMedical ? 'medical_review' : 'recommendation1';
        $exeat = ExeatRequest::create([
            'student_id' => $user->id,
            'matric_no' => $studentAcademic ? $studentAcademic->matric_no : null,
            'category_id' => $validated['category_id'],
            'reason' => $validated['reason'],
            'destination' => $validated['destination'],
            'departure_date' => $validated['departure_date'],
            'return_date' => $validated['return_date'],
            'preferred_mode_of_contact' => $validated['preferred_mode_of_contact'],
            'parent_surname' => $studentContact ? $studentContact->surname : null,
            'parent_othernames' => $studentContact ? $studentContact->other_names : null,
            'parent_phone_no' => $studentContact ? $studentContact->phone_no : null,
            'parent_phone_no_two' => $studentContact ? $studentContact->phone_no_two : null,
            'parent_email' => $studentContact ? $studentContact->email : null,
            'student_accommodation' => $accommodation,
            'status' => $initialStatus,
            'is_medical' => $isMedical,
        ]);
        // Create first approval stage
        \App\Models\ExeatApproval::create([
            'exeat_request_id' => $exeat->id,
            'role' => $isMedical ? 'medical_officer' : 'deputy_dean',
            'status' => 'pending',
        ]);
        Log::info('Student created exeat request', ['student_id' => $user->id, 'exeat_id' => $exeat->id]);
        return response()->json(['message' => 'Exeat request created successfully.', 'exeat_request' => $exeat], 201);
    }


    // GET /api/student/profile
public function profile(Request $request)
{
    $user = $request->user();

    $studentAcademic = StudentAcademic::where('student_id', $user->id)->first();
    $studentContact = StudentContact::where('student_id', $user->id)->first();
    $accommodationHistory = VunaAccomodationHistory::where('student_id', $user->id)
        ->orderBy('created_at', 'desc')->first();

    $accommodation = null;
    if ($accommodationHistory) {
        $accommodationModel = VunaAccomodation::find($accommodationHistory->vuna_accomodation_id);
        $accommodation = $accommodationModel ? $accommodationModel->name : null;
    }

    return response()->json([
        'profile' => [
            'matric_no' => $studentAcademic?->matric_no,
            'parent_surname' => $studentContact?->surname,
            'parent_othernames' => $studentContact?->other_names,
            'parent_phone_no' => $studentContact?->phone_no,
            'parent_phone_no_two' => $studentContact?->phone_no_two,
            'parent_email' => $studentContact?->email,
            'student_accommodation' => $accommodation,
        ]
    ]);
}

public function categories()
{
    return response()->json([
        'categories' => ExeatCategory::all(['id', 'name'])
    ]);
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
