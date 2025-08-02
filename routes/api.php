<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\StudentExeatRequestController;
use App\Http\Controllers\StaffExeatRequestController;
use App\Http\Controllers\ParentConsentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ChatController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::get('/parent/exeat-consent/{token}/{action}', [ParentConsentController::class, 'handleWebConsent']);
// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // User profile

    Route::get('/me', [\App\Http\Controllers\MeController::class, 'me']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/password', [AuthController::class, 'updatePassword']);

    // Student routes
    Route::prefix('student')->group(function () {
        Route::get('/exeat-requests', [StudentExeatRequestController::class, 'index']);
        Route::post('/exeat-requests', [StudentExeatRequestController::class, 'store']);
        Route::get('/exeat-requests/{id}', [StudentExeatRequestController::class, 'show']);
        Route::get('/exeat-requests/{id}/history', [StudentExeatRequestController::class, 'history']);
    });

    // Staff routes
    Route::prefix('staff')->group(function () {
        Route::get('/dashboard', [StaffExeatRequestController::class, 'dashboard']);
        Route::get('/exeat-requests', [StaffExeatRequestController::class, 'index']);
        Route::get('/exeat-requests/{id}', [StaffExeatRequestController::class, 'show']);
        Route::post('/exeat-requests/{id}/approve', [StaffExeatRequestController::class, 'approve']);
        Route::post('/exeat-requests/{id}/reject', [StaffExeatRequestController::class, 'reject']);
        Route::post('/exeat-requests/{id}/send-parent-consent', [StaffExeatRequestController::class, 'sendParentConsent']);
        Route::get('/exeat-requests/{id}/history', [StaffExeatRequestController::class, 'history']);
    });

    // Parent consent routes
    Route::prefix('parent')->group(function () {
        Route::post('/consent/{token}/approve', [ParentConsentController::class, 'approve']);
        Route::post('/consent/{token}/decline', [ParentConsentController::class, 'decline']);
    });

    // Admin routes
    Route::middleware('role:admin')->group(function () {
        // Staff management
        Route::apiResource('staffs', StaffController::class);
        Route::post('/staffs/{id}/roles', [StaffController::class, 'assignRoles']);
        Route::get('/staffs/{id}/roles', [StaffController::class, 'getRoles']);

        // Student management
        Route::apiResource('students', StudentController::class);
        Route::post('/students/import', [StudentController::class, 'import']);
        Route::get('/students/export', [StudentController::class, 'export']);

        // Role management
        Route::apiResource('roles', RoleController::class);
        Route::get('/permissions', [RoleController::class, 'permissions']);

        // System configuration
        Route::get('/config', [ConfigController::class, 'index']);
        Route::put('/config', [ConfigController::class, 'update']);

        // Exeat management
        Route::get('/exeats', [StaffExeatRequestController::class, 'all']);
        Route::get('/exeats/export', [StaffExeatRequestController::class, 'export']);
    });

    // Dean routes
    Route::middleware('role:dean')->group(function () {
        Route::get('/dean/dashboard', [StaffExeatRequestController::class, 'deanDashboard']);
        Route::get('/dean/exeat-requests', [StaffExeatRequestController::class, 'deanRequests']);
    });

    // CMD routes
    Route::middleware('role:cmd')->group(function () {
        Route::get('/cmd/dashboard', [StaffExeatRequestController::class, 'cmdDashboard']);
        Route::get('/cmd/exeat-requests', [StaffExeatRequestController::class, 'cmdRequests']);
    });

    // Hostel routes
    Route::middleware('role:hostel_admin')->group(function () {
        Route::get('/hostel/dashboard', [StaffExeatRequestController::class, 'hostelDashboard']);
        Route::get('/hostel/exeat-requests', [StaffExeatRequestController::class, 'hostelRequests']);
    });

    // Security routes
    Route::middleware('role:security')->group(function () {
        Route::get('/security/dashboard', [StaffExeatRequestController::class, 'securityDashboard']);
        Route::get('/security/exeat-requests', [StaffExeatRequestController::class, 'securityRequests']);
    });

    // Lookup routes
    Route::get('/lookup/departments', [ConfigController::class, 'departments']);
    Route::get('/lookup/hostels', [ConfigController::class, 'hostels']);
    Route::get('/lookup/roles', [ConfigController::class, 'roles']);

    // Analytics routes
    Route::get('/analytics/exeat-usage', [ReportController::class, 'exeatUsage']);
    Route::get('/analytics/student-trends', [ReportController::class, 'studentTrends']);
    Route::get('/analytics/staff-performance', [ReportController::class, 'staffPerformance']);

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/mark-read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // Communication routes
    Route::post('/send-email', [CommunicationController::class, 'sendEmail']);
    Route::post('/send-sms', [CommunicationController::class, 'sendSMS']);

    // Chat routes
    Route::get('/chats', [ChatController::class, 'index']);
    Route::post('/chats', [ChatController::class, 'store']);
    Route::get('/chats/{id}', [ChatController::class, 'show']);
    Route::post('/chats/{id}/messages', [ChatController::class, 'sendMessage']);
});

