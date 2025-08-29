<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\BirthdayController;
use Illuminate\Http\Request;
use App\Services\ExeatNotificationService;
use App\Models\User;
use App\Models\ExeatRequest;
use Illuminate\Support\Facades\DB;

Route::get('/send-specific-birthday', [BirthdayController::class, 'sendBirthdayEmailToSpecificUsers']);


Route::get('/', function () {
    return Redirect::to('/status');
});

Route::get('/status', function () {
    $list = Cache::get('api_status_list', []);
    return view('status', ['status_list' => $list]);
});

Route::get('/test-notifications', function (Request $request) {
    try {
        // Check current notification count
        $initialCount = DB::table('exeat_notifications')->count();
        
        // Get a test student and exeat request
        $student = User::where('role', 'student')->first();
        if (!$student) {
            return response()->json([
                'error' => 'No student found for testing',
                'initial_count' => $initialCount
            ]);
        }
        
        $exeatRequest = ExeatRequest::where('student_id', $student->id)->first();
        if (!$exeatRequest) {
            return response()->json([
                'error' => 'No exeat request found for student',
                'student_id' => $student->id,
                'initial_count' => $initialCount
            ]);
        }
        
        // Test notification service
        $notificationService = new ExeatNotificationService();
        
        // Send a test stage change notification
        $notificationService->sendStageChangeNotification($exeatRequest, $student);
        
        // Check new count
        $finalCount = DB::table('exeat_notifications')->count();
        
        // Get the latest notification
        $latestNotification = DB::table('exeat_notifications')
            ->orderBy('created_at', 'desc')
            ->first();
        
        return response()->json([
            'success' => true,
            'initial_count' => $initialCount,
            'final_count' => $finalCount,
            'notifications_created' => $finalCount - $initialCount,
            'student' => [
                'id' => $student->id,
                'name' => $student->fname . ' ' . $student->lname,
                'matric_no' => $student->matric_no ?? 'N/A'
            ],
            'exeat_request' => [
                'id' => $exeatRequest->id,
                'status' => $exeatRequest->status
            ],
            'latest_notification' => $latestNotification ? [
                'id' => $latestNotification->id,
                'type' => $latestNotification->notification_type,
                'title' => $latestNotification->title,
                'message' => $latestNotification->message,
                'user_id' => $latestNotification->recipient_id,
                'exeat_request_id' => $latestNotification->exeat_request_id
            ] : null
        ]);
        
    } catch (\Exception $e) {
         return response()->json([
             'error' => 'Exception occurred: ' . $e->getMessage(),
             'trace' => $e->getTraceAsString()
         ], 500);
     }
});



