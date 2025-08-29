<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HostelSignout;
use App\Models\ExeatRequest;
use App\Services\ExeatWorkflowService;
use Illuminate\Support\Facades\Log;

class HostelController extends Controller
{
    protected $workflowService;

    public function __construct(ExeatWorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
        $this->middleware('auth:sanctum');
    }

    // Note: Hostel signout/signin is now handled through StaffExeatRequestController approve method

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
