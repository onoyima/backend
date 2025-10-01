<?php

use Illuminate\Support\Facades\Route;

// Simple test route without any dependencies
Route::get('/test', function () {
    return response()->json(['status' => 'success', 'message' => 'Laravel is working!']);
});
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\BirthdayController;
use Illuminate\Http\Request;
use App\Services\ExeatNotificationService;
use App\Services\UrlShortenerService;
use App\Models\User;
use App\Models\ExeatRequest;
use Illuminate\Support\Facades\DB;

Route::get('/send-specific-birthday', [BirthdayController::class, 'sendBirthdayEmailToSpecificUsers']);

// URL Shortener redirect route
Route::get('/s/{shortCode}', function ($shortCode, UrlShortenerService $urlShortener) {
    $originalUrl = $urlShortener->resolveUrl($shortCode);

    if ($originalUrl) {
        return redirect($originalUrl);
    }

    return abort(404, 'Short URL not found or expired');
});

// Admin Command Routes
use App\Http\Controllers\AdminCommandController;

Route::prefix('admin/commands')->group(function () {
    Route::get('/', [AdminCommandController::class, 'index'])->name('admin.commands.index');
    Route::post('/check-overdue', [AdminCommandController::class, 'checkOverdue'])->name('admin.commands.check-overdue');
    Route::post('/expire-overdue', [AdminCommandController::class, 'expireOverdue'])->name('admin.commands.expire-overdue');
    Route::post('/run-all', [AdminCommandController::class, 'runAll'])->name('admin.commands.run-all');
});
Route::get('/', function () {
    return Redirect::to('/status');
});

// Include demo routes for consent pages (remove in production)
require __DIR__.'/demo.php';

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



