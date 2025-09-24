<?php

namespace App\Http\Controllers;

use App\Models\HostelAdminAssignment;
use App\Models\VunaAccomodation;
use App\Models\Staff;
use App\Models\ExeatRole;
use App\Models\StaffExeatRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HostelAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get all hostel admin assignments
     */
    public function index(Request $request)
    {
        $assignments = HostelAdminAssignment::with(['hostel', 'staff'])
            ->when($request->has('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->has('hostel_id'), function ($query) use ($request) {
                $query->where('vuna_accomodation_id', $request->hostel_id);
            })
            ->when($request->has('staff_id'), function ($query) use ($request) {
                $query->where('staff_id', $request->staff_id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $assignments
        ]);
    }

    /**
     * Get available hostels and staff for assignment
     */
    public function getAssignmentOptions(Request $request)
    {
        $hostels = VunaAccomodation::select('id', 'name', 'gender')
            ->orderBy('name')
            ->paginate($request->get('per_page', 15));

        $staff = Staff::select('id', 'fname', 'lname', 'email')
            ->orderBy('fname')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => [
                'hostels' => $hostels,
                'staff' => $staff
            ]
        ]);
    }

    /**
     * Assign a hostel to a staff member
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'vuna_accomodation_id' => 'required|exists:vuna_accomodations,id',
            'staff_id' => 'required|exists:staff,id',
            'auto_assign_role' => 'boolean',
            'notes' => 'nullable|string|max:1000'
        ]);

        DB::beginTransaction();
        try {
            // Check if assignment already exists
            $existingAssignment = HostelAdminAssignment::where('vuna_accomodation_id', $validated['vuna_accomodation_id'])
                ->where('staff_id', $validated['staff_id'])
                ->where('status', 'active')
                ->first();

            if ($existingAssignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This staff member is already assigned to this hostel.'
                ], 422);
            }

            // Create hostel assignment
            $assignment = HostelAdminAssignment::create([
                'vuna_accomodation_id' => $validated['vuna_accomodation_id'],
                'staff_id' => $validated['staff_id'],
                'assigned_at' => now(),
                'status' => 'active',
                'assigned_by' => $request->user()->id,
                'notes' => $validated['notes'] ?? null
            ]);

            // Auto-assign hostel_admin role if requested
            if ($validated['auto_assign_role'] ?? true) {
                $hostelAdminRole = ExeatRole::where('name', 'hostel_admin')->first();
                
                if ($hostelAdminRole) {
                    $existingRole = StaffExeatRole::where('staff_id', $validated['staff_id'])
                        ->where('exeat_role_id', $hostelAdminRole->id)
                        ->first();

                    if (!$existingRole) {
                        StaffExeatRole::create([
                            'staff_id' => $validated['staff_id'],
                            'exeat_role_id' => $hostelAdminRole->id,
                            'assigned_at' => now()
                        ]);
                    }
                }
            }

            DB::commit();

            $assignment->load(['hostel', 'staff']);

            Log::info('Hostel admin assignment created', [
                'assignment_id' => $assignment->id,
                'hostel_id' => $validated['vuna_accomodation_id'],
                'staff_id' => $validated['staff_id'],
                'assigned_by' => $request->user()->id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Hostel admin assignment created successfully.',
                'data' => $assignment
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create hostel admin assignment', [
                'error' => $e->getMessage(),
                'hostel_id' => $validated['vuna_accomodation_id'],
                'staff_id' => $validated['staff_id']
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create hostel admin assignment.'
            ], 500);
        }
    }

    /**
     * Update hostel admin assignment
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,inactive'
        ]);

        $assignment = HostelAdminAssignment::findOrFail($id);
        $assignment->update($validated);

        Log::info('Hostel admin assignment updated', [
            'assignment_id' => $id,
            'new_status' => $validated['status'],
            'updated_by' => $request->user()->id
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Hostel admin assignment updated successfully.',
            'data' => $assignment->load(['hostel', 'staff'])
        ]);
    }

    /**
     * Remove hostel admin assignment
     */
    public function destroy($id)
    {
        $assignment = HostelAdminAssignment::findOrFail($id);
        $assignment->update(['status' => 'inactive']);

        Log::info('Hostel admin assignment deactivated', [
            'assignment_id' => $id,
            'hostel_id' => $assignment->vuna_accomodation_id,
            'staff_id' => $assignment->staff_id
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Hostel admin assignment removed successfully.'
        ]);
    }

    /**
     * Get staff member's hostel assignments
     */
    public function getStaffAssignments($staffId)
    {
        $assignments = HostelAdminAssignment::where('staff_id', $staffId)
            ->where('status', 'active')
            ->with(['hostel'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $assignments
        ]);
    }

    /**
     * Get hostel's assigned staff members
     */
    public function getHostelStaff($hostelId)
    {
        $assignments = HostelAdminAssignment::where('vuna_accomodation_id', $hostelId)
            ->where('status', 'active')
            ->with(['staff'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $assignments
        ]);
    }
}