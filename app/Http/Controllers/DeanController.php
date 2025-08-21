<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExeatRequest;
use App\Models\ParentConsent;
use App\Services\ExeatWorkflowService;
use App\Services\ExeatNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class DeanController extends Controller
{
    protected $workflowService;
    protected $notificationService;

    public function __construct(
        ExeatWorkflowService $workflowService,
        ExeatNotificationService $notificationService
    ) {
        $this->workflowService = $workflowService;
        $this->notificationService = $notificationService;
    }
    // GET /api/dean/exeat-requests
    public function index(Request $request)
    {
        // For demo: return all approved/verified exeat requests
        $exeats = ExeatRequest::where('status', 'approved')
            ->with('student:id,fname,lname,passport')
            ->orderBy('created_at', 'desc')
            ->get();
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
