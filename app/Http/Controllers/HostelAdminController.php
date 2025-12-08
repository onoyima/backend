<?php

namespace App\Http\Controllers;

use App\Models\HostelAdminAssignment;
use App\Models\VunaAccomodation;
use App\Models\Staff;
use App\Models\ExeatRole;
use App\Models\StaffExeatRole;
use App\Models\AuditLog;
use App\Models\ExeatRequest;
use App\Models\ExeatNotification;
use App\Services\ExeatNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HostelAdminController extends Controller
{
    protected $notificationService;

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
                $existingAssignment->load(['hostel','staff']);
                return response()->json([
                    'status' => 'success',
                    'message' => 'Hostel admin assignment already exists.',
                    'data' => $existingAssignment
                ], 200);
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

            // Create audit log for hostel assignment
            AuditLog::create([
                'staff_id' => $request->user()->id,
                'student_id' => null,
                'action' => 'hostel_assignment_created',
                'target_type' => 'hostel_assignment',
                'target_id' => $assignment->id,
                'details' => json_encode([
                    'hostel_id' => $validated['vuna_accomodation_id'],
                    'hostel_name' => $assignment->hostel->name ?? 'Unknown',
                    'assigned_staff_id' => $validated['staff_id'],
                    'assigned_staff_name' => $assignment->staff->fname . ' ' . $assignment->staff->lname,
                    'auto_assign_role' => $validated['auto_assign_role'] ?? true,
                    'notes' => $validated['notes'] ?? null
                ])
            ]);

            DB::commit();

            $assignment->load(['hostel', 'staff']);

            Log::info('Hostel admin assignment created', [
                'assignment_id' => $assignment->id,
                'hostel_id' => $validated['vuna_accomodation_id'],
                'staff_id' => $validated['staff_id'],
                'assigned_by' => $request->user()->id
            ]);

            // Notify assigned staff of hostel assignment update (live via SSE) — errors are non-blocking
            try {
                $dummyExeat = new ExeatRequest(['id' => 0]);
                app(ExeatNotificationService::class)->createNotification(
                    $dummyExeat,
                    [[
                        'type' => ExeatNotification::RECIPIENT_STAFF,
                        'id' => $validated['staff_id']
                    ]],
                    ExeatNotification::TYPE_REMINDER,
                    'Hostel Assignment Updated',
                    'Your hostel assignments were updated and are now effective.',
                    ExeatNotification::PRIORITY_HIGH,
                    [ 'event' => 'hostel_assignment_updated' ]
                );
            } catch (\Throwable $e) {
                Log::warning('Hostel assignment created but failed to send live SSE notification', [
                    'assignment_id' => $assignment->id,
                    'error' => $e->getMessage()
                ]);
            }

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

        $assignment = HostelAdminAssignment::with(['hostel','staff'])->findOrFail($id);
        $oldStatus = $assignment->status;
        $assignment->update($validated);

        // Create audit log for hostel assignment update
        AuditLog::create([
            'staff_id' => $request->user()->id,
            'student_id' => null,
            'action' => 'hostel_assignment_updated',
            'target_type' => 'hostel_assignment',
            'target_id' => $assignment->id,
            'details' => json_encode([
                'hostel_id' => $assignment->vuna_accomodation_id,
                'hostel_name' => $assignment->hostel->name ?? 'Unknown',
                'assigned_staff_id' => $assignment->staff_id,
                'assigned_staff_name' => $assignment->staff->fname . ' ' . $assignment->staff->lname,
                'status_changed_from' => $oldStatus,
                'status_changed_to' => $validated['status']
            ])
        ]);

        Log::info('Hostel admin assignment updated', [
            'assignment_id' => $id,
            'new_status' => $validated['status'],
            'updated_by' => $request->user()->id
        ]);

        // Notify staff of hostel assignment status change (live via SSE) — errors are non-blocking
        try {
            $dummyExeat = new ExeatRequest(['id' => 0]);
            app(ExeatNotificationService::class)->createNotification(
                $dummyExeat,
                [[
                    'type' => ExeatNotification::RECIPIENT_STAFF,
                    'id' => $assignment->staff_id
                ]],
                ExeatNotification::TYPE_REMINDER,
                'Hostel Assignment Updated',
                'Your hostel assignment status changed and is now effective.',
                ExeatNotification::PRIORITY_MEDIUM,
                [ 'event' => 'hostel_assignment_updated' ]
            );
        } catch (\Throwable $e) {
            Log::warning('Hostel assignment status updated but failed to send live SSE notification', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Hostel admin assignment updated successfully.',
            'data' => $assignment->load(['hostel', 'staff'])
        ]);
    }

    /**
     * Remove hostel admin assignment
     */
    public function destroy(Request $request, $id)
    {
        $assignment = HostelAdminAssignment::with(['hostel', 'staff'])->findOrFail($id);

        // Create audit log for hostel assignment removal
        AuditLog::create([
            'staff_id' => $request->user()->id,
            'student_id' => null,
            'action' => 'hostel_assignment_removed',
            'target_type' => 'hostel_assignment',
            'target_id' => $assignment->id,
            'details' => json_encode([
                'hostel_id' => $assignment->vuna_accomodation_id,
                'hostel_name' => $assignment->hostel->name ?? 'Unknown',
                'assigned_staff_id' => $assignment->staff_id,
                'assigned_staff_name' => $assignment->staff->fname . ' ' . $assignment->staff->lname,
                'removed_by' => $request->user()->fname . ' ' . $request->user()->lname
            ])
        ]);

        // Perform hard delete
        $assignment->delete();

        Log::info('Hostel admin assignment deleted', [
            'assignment_id' => $id,
            'hostel_id' => $assignment->vuna_accomodation_id,
            'staff_id' => $assignment->staff_id
        ]);

        // Notify staff of hostel assignment removal (live via SSE) — errors are non-blocking
        try {
            $dummyExeat = new ExeatRequest(['id' => 0]);
            app(ExeatNotificationService::class)->createNotification(
                $dummyExeat,
                [[
                    'type' => ExeatNotification::RECIPIENT_STAFF,
                    'id' => $assignment->staff_id
                ]],
                ExeatNotification::TYPE_REMINDER,
                'Hostel Assignment Updated',
                'A hostel assignment was removed from your profile.',
                ExeatNotification::PRIORITY_MEDIUM,
                [ 'event' => 'hostel_assignment_updated' ]
            );
        } catch (\Throwable $e) {
            Log::warning('Hostel assignment removed but failed to send live SSE notification', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage()
            ]);
        }

        // If no other active hostel assignments remain, and the hostel_admin role was not assigned individually,
        // remove the hostel_admin exeat role from this staff
        $hasOtherActiveAssignments = HostelAdminAssignment::where('staff_id', $assignment->staff_id)
            ->where('status', 'active')
            ->exists();

        if (!$hasOtherActiveAssignments) {
            $hostelAdminRole = ExeatRole::where('name', 'hostel_admin')->first();
            if ($hostelAdminRole) {
                // Determine if the hostel_admin role was assigned individually (via admin role assignment)
                $manualRoleAssigned = false;
                $roleAssignedLogs = AuditLog::where('action', 'role_assigned')
                    ->where('target_type', 'staff_role')
                    ->orderBy('id', 'desc')
                    ->get();
                foreach ($roleAssignedLogs as $log) {
                    $details = json_decode($log->details ?? '[]', true);
                    if (is_array($details)
                        && ($details['assigned_staff_id'] ?? null) == $assignment->staff_id
                        && (
                            ($details['role_name'] ?? null) === 'hostel_admin'
                            || ($details['role_id'] ?? null) == $hostelAdminRole->id
                        )
                    ) {
                        $manualRoleAssigned = true;
                        break;
                    }
                }

                if (!$manualRoleAssigned) {
                    StaffExeatRole::where('staff_id', $assignment->staff_id)
                        ->where('exeat_role_id', $hostelAdminRole->id)
                        ->delete();

                    Log::info('Hostel admin exeat role removed due to assignment deletion and no remaining assignments', [
                        'staff_id' => $assignment->staff_id,
                        'exeat_role_id' => $hostelAdminRole->id
                    ]);

                    // Notify staff that their roles were updated — errors are non-blocking
                    try {
                        app(ExeatNotificationService::class)->createNotification(
                            $dummyExeat,
                            [[
                                'type' => ExeatNotification::RECIPIENT_STAFF,
                                'id' => $assignment->staff_id
                            ]],
                            ExeatNotification::TYPE_REMINDER,
                            'Role Updated',
                            'Your hostel admin role was removed because you have no active hostel assignments.',
                            ExeatNotification::PRIORITY_MEDIUM,
                            [ 'event' => 'roles_updated' ]
                        );
                    } catch (\Throwable $e) {
                        Log::warning('Hostel admin role removed but failed to send live SSE notification', [
                            'staff_id' => $assignment->staff_id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }

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