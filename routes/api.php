<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// CORS test endpoint
Route::options('cors-test', function () {
    return response()->json(['message' => 'CORS test successful'], 200);
});

Route::get('cors-test', function () {
    return response()->json([
        'message' => 'CORS test successful',
        'timestamp' => now(),
        'origin' => request()->header('Origin'),
        'headers' => request()->headers->all()
    ], 200);
});

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentExeatDebtController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\AdminRoleController;
use App\Http\Controllers\AdminConfigController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\StudentExeatRequestController;
use App\Http\Controllers\StaffExeatRequestController;
use App\Http\Controllers\ParentConsentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ChatController;
// use App\Http\Controllers\CommunicationApprovalController; // Controller missing
// use App\Http\Controllers\CommunicationAnalyticsController; // Controller missing
use App\Http\Controllers\StudentNotificationController;
use App\Http\Controllers\StaffNotificationController;
use App\Http\Controllers\AdminNotificationController;
use App\Http\Controllers\DeanNotificationController;
use App\Http\Controllers\DeanController;
use App\Http\Controllers\ExeatHistoryController;
use App\Http\Controllers\StaffExeatStatisticsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AdminBulkOperationsController;
use App\Http\Controllers\AdminExeatController;
use App\Http\Controllers\AdminStudentDebtController;
use App\Http\Controllers\HostelAdminController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthorized'], 401);
})->name('login');
Route::post('/register', [AuthController::class, 'register']);

Route::get('/parent/exeat-consent/{token}/{action}', [ParentConsentController::class, 'handleWebConsent']);
Route::get('/parent/consent/{token}/approve', function($token) {
    return app(App\Http\Controllers\ParentConsentController::class)->handleWebConsent($token, 'approve');
});
Route::get('/parent/consent/{token}/decline', function($token) {
    return app(App\Http\Controllers\ParentConsentController::class)->handleWebConsent($token, 'decline');
});

// Public payment verification route (no auth required for Paystack callback)
Route::get('student/debts/{id}/verify-payment', [StudentExeatDebtController::class, 'verifyPayment'])->name('student.debts.verify-payment');

// Public API payment verification route (returns JSON for programmatic access)
Route::get('student/debts/{id}/verify-payment-api', [StudentExeatDebtController::class, 'verifyPaymentApi'])->name('student.debts.verify-payment-api');

// Generic payment endpoints (similar to NYSC payment system)
Route::get('student/debts/payment/verify', [StudentExeatDebtController::class, 'verifyPaymentGeneric'])->name('student.debts.payment.verify');
Route::post('student/debts/payment/webhook', [StudentExeatDebtController::class, 'paymentWebhook'])->name('student.debts.payment.webhook');

// Test redirect route
Route::get('test-redirect', function() {
    $frontendUrl = config('app.frontend_url') . '/payment/result?' . http_build_query([
        'status' => 'test',
        'message' => 'This is a test redirect'
    ]);
    return redirect($frontendUrl);
});

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
        Route::get('/exeat-requests/comments', [StudentExeatRequestController::class, 'comments']);
        Route::post('/exeat-requests', [StudentExeatRequestController::class, 'store']);
        Route::get('/exeat-requests/{id}', [StudentExeatRequestController::class, 'show']);
        Route::get('/exeat-requests/{id}/history', [StudentExeatRequestController::class, 'history']);
      // ✅ ADD THESE TWO ROUTES:
        Route::get('/exeat-categories', [StudentExeatRequestController::class, 'categories']);
        Route::get('/profile', [StudentExeatRequestController::class, 'profile']);
        
        // Student debt routes
        Route::prefix('debts')->group(function () {
            Route::get('/', [StudentExeatDebtController::class, 'index']);
            Route::get('/{id}', [StudentExeatDebtController::class, 'show']);
            Route::post('/{id}/payment-proof', [StudentExeatDebtController::class, 'updatePaymentProof']);
            Route::post('/{id}/payment-generic', [StudentExeatDebtController::class, 'initializePaymentGeneric']);
        });

        // Student notification routes
        Route::prefix('notifications')->group(function () {
            Route::get('/', [StudentNotificationController::class, 'index']);
            Route::get('/unread-count', [StudentNotificationController::class, 'getUnreadCount']);
            Route::post('/{id}/mark-read', [StudentNotificationController::class, 'markAsRead']);
            Route::get('/preferences', [StudentNotificationController::class, 'getPreferences']);
            Route::put('/preferences', [StudentNotificationController::class, 'updatePreferences']);
            Route::post('/preferences/reset', [StudentNotificationController::class, 'resetPreferences']);
            Route::get('/{id}', [StudentNotificationController::class, 'show']);
            Route::get('/exeat/{exeatId}', [StudentNotificationController::class, 'getExeatNotifications']);
            Route::get('/statistics/overview', [StudentNotificationController::class, 'getStatistics']);
            Route::post('/test', [StudentNotificationController::class, 'testNotification']);
        });
    });

    // Staff routes
    Route::prefix('staff')->group(function () {
        Route::get('/dashboard', [StaffExeatRequestController::class, 'dashboard']);
        Route::get('/exeat-requests', [StaffExeatRequestController::class, 'index']);
        Route::get('/exeat-requests/export', [StaffExeatRequestController::class, 'export']);
        Route::get('/exeat-requests/{id}', [StaffExeatRequestController::class, 'show']);
        Route::put('/exeat-requests/{id}', [StaffExeatRequestController::class, 'edit']);
        Route::post('/exeat-requests/{id}/approve', [StaffExeatRequestController::class, 'approve']);
        Route::post('/exeat-requests/{id}/reject', [StaffExeatRequestController::class, 'reject']);
        Route::post('/exeat-requests/{id}/send-parent-consent', [StaffExeatRequestController::class, 'sendParentConsent']);
        Route::post('/exeat-requests/{id}/send-comment', [StaffExeatRequestController::class, 'sendComment']);
        Route::get('/exeat-requests/{id}/history', [StaffExeatRequestController::class, 'history']);
        Route::get('/exeat-requests/role-history', [StaffExeatRequestController::class, 'roleHistory']);

        // Staff exeat statistics routes
        Route::get('/exeat-statistics', [StaffExeatStatisticsController::class, 'getExeatStatistics']);
        Route::get('/exeat-statistics/detailed', [StaffExeatStatisticsController::class, 'getDetailedStatistics']);
        Route::get('/exeat-history', [StaffExeatStatisticsController::class, 'getStaffExeatHistory']);

        // Staff notification routes
        Route::prefix('notifications')->group(function () {
            Route::get('/', [StaffNotificationController::class, 'index']);
            Route::get('/unread-count', [StaffNotificationController::class, 'unreadCount']);
            Route::post('/mark-read', [StaffNotificationController::class, 'markAsRead']);
            Route::get('/preferences', [StaffNotificationController::class, 'getPreferences']);
            Route::put('/preferences', [StaffNotificationController::class, 'updatePreferences']);
            Route::get('/pending-approvals', [StaffNotificationController::class, 'getPendingApprovals']);
            Route::post('/send-to-student', [StaffNotificationController::class, 'sendNotificationToStudent']);
            Route::post('/send-reminder', [StaffNotificationController::class, 'sendReminderNotification']);
            Route::post('/send-emergency', [StaffNotificationController::class, 'sendEmergencyNotification']);
            Route::get('/statistics/overview', [StaffNotificationController::class, 'getStatistics']);
            Route::get('/{id}', [StaffNotificationController::class, 'show']);
            Route::get('/exeat/{exeatId}', [StaffNotificationController::class, 'getExeatNotifications']);
        });

        Route::get('/gate-events', [StaffExeatRequestController::class, 'gateEvents'])->middleware('role:hostel_admin');
        Route::get('/gate-events/export', [StaffExeatRequestController::class, 'gateEventsExport'])->middleware('role:hostel_admin');
    });

    Route::get('/notifications/alert-audio', function () {
        $candidates = [
            storage_path('app/alert.mp3'),
            storage_path('app/public/alert.mp3'),
            public_path('alert.mp3'),
            public_path('storage/alert.mp3'),
            base_path('front/public/alert.mp3'),
        ];
        $path = null;
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) { $path = $candidate; break; }
        }
        if (!$path) {
            return response()->json(['message' => 'Audio not found'], 404);
        }
        return response()->file($path, [
            'Content-Type' => 'audio/mpeg'
        ]);
    });

    Route::get('/staff/notifications/stream', [StaffNotificationController::class, 'stream']);

    Route::get('/config/hostel-stages', function () {
        return response()->json([
            'hostel_stages_enabled' => (bool) config('app.hostel_stages_enabled')
        ]);
    });

    // Parent consent routes
    Route::prefix('parent')->group(function () {
        Route::post('/consent/{token}/approve', [ParentConsentController::class, 'approve']);
        Route::post('/consent/{token}/decline', [ParentConsentController::class, 'decline']);
    });

    // Admin routes

 Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    Route::get('/roles', [AdminRoleController::class, 'index']);
    Route::post('/roles', [AdminRoleController::class, 'store']);
    Route::put('/roles/{id}', [AdminRoleController::class, 'update']);
    Route::delete('/roles/{id}', [AdminRoleController::class, 'destroy']);

    Route::get('/staff', [AdminStaffController::class, 'index']);
    Route::post('/staff', [AdminStaffController::class, 'store']);
    Route::get('/staff/assignments', [AdminStaffController::class, 'assignments']);
    Route::get('/staff/{id}', [AdminStaffController::class, 'show']);
    Route::put('/staff/{id}', [AdminStaffController::class, 'update']);
    Route::delete('/staff/{id}', [AdminStaffController::class, 'destroy']);
    Route::post('/staff/{id}/assign-exeat-role', [AdminStaffController::class, 'assignExeatRole']);
    Route::delete('/staff/{id}/unassign-exeat-role', [AdminStaffController::class, 'unassignExeatRole']);
    Route::put('/exeat-requests/{id}', [AdminExeatController::class, 'edit']);
    
    // Student debt routes (Admin management)
    Route::prefix('student-debts')->group(function () {
        Route::get('/', [AdminStudentDebtController::class, 'index']);
        Route::get('/{id}', [AdminStudentDebtController::class, 'show']);
        Route::post('/{id}/clear', [AdminStudentDebtController::class, 'clearDebt']);
    });

    // Hostel admin assignment routes
    Route::prefix('hostel-assignments')->group(function () {
        Route::get('/', [HostelAdminController::class, 'index']);
        Route::get('/options', [HostelAdminController::class, 'getAssignmentOptions']);
        Route::post('/', [HostelAdminController::class, 'store']);
        Route::put('/{id}', [HostelAdminController::class, 'update']);
        Route::delete('/{id}', [HostelAdminController::class, 'destroy']);
        Route::get('/staff/{staffId}', [HostelAdminController::class, 'getStaffAssignments']);
        Route::get('/hostel/{hostelId}', [HostelAdminController::class, 'getHostelStaff']);
    });

    // Admin notification routes
    Route::prefix('notifications')->group(function () {
        Route::get('/', [AdminNotificationController::class, 'index']);
        Route::get('/statistics', [AdminNotificationController::class, 'getStats']);
        Route::get('/unread-count', [AdminNotificationController::class, 'unreadCount']);
        Route::post('/bulk-send', [AdminNotificationController::class, 'sendBulkNotification']);
        Route::get('/delivery-logs', [AdminNotificationController::class, 'getDeliveryLogs']);
        Route::post('/retry-failed', [AdminNotificationController::class, 'retryFailedDeliveries']);
        Route::get('/user-preferences/{userId}', [AdminNotificationController::class, 'getUserPreferences']);
        Route::put('/user-preferences/{userId}', [AdminNotificationController::class, 'updateUserPreferences']);
        Route::get('/preferences-statistics', [AdminNotificationController::class, 'getPreferencesStatistics']);
        Route::post('/clear-preferences-cache', [AdminNotificationController::class, 'clearPreferencesCache']);
        Route::get('/templates', [AdminNotificationController::class, 'getNotificationTemplates']);
    });

    // Admin bulk operations routes
    Route::prefix('bulk-operations')->group(function () {
        Route::get('/exeat-requests', [AdminBulkOperationsController::class, 'getFilteredRequests']);
        Route::post('/bulk-approve', [AdminBulkOperationsController::class, 'bulkApprove']);
        Route::post('/bulk-reject', [AdminBulkOperationsController::class, 'bulkReject']);
        Route::post('/special-dean-override', [AdminBulkOperationsController::class, 'specialDeanOverride']);
        Route::get('/statistics', [AdminBulkOperationsController::class, 'getStatistics']);
    });
});

    // Dean routes
    Route::middleware('role:dean')->group(function () {
        Route::get('/dean/dashboard', [StaffExeatRequestController::class, 'deanDashboard']);
        Route::get('/dean/exeat-requests', [StaffExeatRequestController::class, 'deanRequests']);
        Route::post('/dean/exeat-requests', [DeanController::class, 'storeForStudent']);
            Route::post('/dean/exeat-requests/bulkAprroval', [DeanController::class, 'bulkAprroval']);
        Route::put('/dean/exeat-requests/{id}', [DeanController::class, 'edit']);
        
        // Dean student debt routes
        Route::prefix('dean/student-debts')->group(function () {
            Route::get('/', [DeanController::class, 'studentDebts']);
            Route::get('/{id}', [DeanController::class, 'showStudentDebt']);
            Route::post('/{id}/clear', [DeanController::class, 'clearStudentDebt']);
        });

        // Dean notification routes
        Route::prefix('dean/notifications')->group(function () {
            Route::get('/', [DeanNotificationController::class, 'index']);
            Route::get('/unread-count', [DeanNotificationController::class, 'getUnreadCount']);
            Route::post('/{id}/mark-read', [DeanNotificationController::class, 'markAsRead']);
            Route::post('/mark-all-read', [DeanNotificationController::class, 'markAllAsRead']);
            Route::get('/preferences', [DeanNotificationController::class, 'getPreferences']);
            Route::put('/preferences', [DeanNotificationController::class, 'updatePreferences']);
            Route::get('/pending-approvals', [DeanNotificationController::class, 'getPendingApprovals']);
            Route::post('/send-to-students', [DeanNotificationController::class, 'sendNotificationToStudents']);
            Route::post('/send-reminder', [DeanNotificationController::class, 'sendReminderNotification']);
            Route::post('/send-emergency', [DeanNotificationController::class, 'sendEmergencyNotification']);
            Route::get('/statistics/overview', [DeanNotificationController::class, 'getStatistics']);
            Route::get('/{id}', [DeanNotificationController::class, 'show']);
            Route::get('/exeat/{exeatId}', [DeanNotificationController::class, 'getExeatNotifications']);
        });

        // Secretary parent consent routes
        Route::prefix('staff/parent-consents')->group(function () {
            Route::get('/pending', [StaffExeatRequestController::class, 'getPendingParentConsents']);
            Route::post('/{consentId}/approve', [StaffExeatRequestController::class, 'approveParentConsent']);
            Route::post('/{consentId}/reject', [StaffExeatRequestController::class, 'rejectParentConsent']);
            Route::get('/statistics', [StaffExeatRequestController::class, 'getParentConsentStats']);
        });

        // Dean bulk operations routes
        Route::prefix('bulk-operations')->group(function () {
            Route::get('/exeat-requests', [AdminBulkOperationsController::class, 'getFilteredRequests']);
            Route::post('/bulk-approve', [AdminBulkOperationsController::class, 'bulkApprove']);
            Route::post('/bulk-reject', [AdminBulkOperationsController::class, 'bulkReject']);
            Route::post('/special-dean-override', [AdminBulkOperationsController::class, 'specialDeanOverride']);
            Route::get('/statistics', [AdminBulkOperationsController::class, 'getStatistics']);
        });

        // Dean hostel admin assignment routes
        Route::prefix('hostel-assignments')->group(function () {
            Route::get('/', [HostelAdminController::class, 'index']);
            Route::get('/options', [HostelAdminController::class, 'getAssignmentOptions']);
            Route::post('/', [HostelAdminController::class, 'store']);
            Route::put('/{id}', [HostelAdminController::class, 'update']);
            Route::delete('/{id}', [HostelAdminController::class, 'destroy']);
            Route::get('/staff/{staffId}', [HostelAdminController::class, 'getStaffAssignments']);
            Route::get('/hostel/{hostelId}', [HostelAdminController::class, 'getHostelStaff']);
        });
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
    Route::get('/lookup/departments', [AdminConfigController::class, 'departments']);
    Route::get('/lookup/hostels', [AdminConfigController::class, 'hostels']);
    Route::get('/lookup/roles', [AdminConfigController::class, 'roles']);

    // Analytics routes
    Route::get('/analytics/exeat-usage', [ReportController::class, 'exeatUsage']);
    Route::get('/analytics/student-trends', [ReportController::class, 'studentTrends']);
    Route::get('/analytics/staff-performance', [ReportController::class, 'staffPerformance']);

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/mark-read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // Exeat History and Statistics routes
    Route::get('/staff/exeat-history', [ExeatHistoryController::class, 'getStaffExeatHistory']);
    Route::get('/exeats/by-status/{status}', [ExeatHistoryController::class, 'getExeatsByStatus']);
    Route::get('/exeats/by-status/{status}/{id}', [ExeatHistoryController::class, 'getExeatByStatusAndId']);
    Route::get('/exeats/statistics', [ExeatHistoryController::class, 'getExeatStatistics']);

    // Communication routes
    Route::post('/send-email', [CommunicationController::class, 'sendEmail']);
    Route::post('/send-sms', [CommunicationController::class, 'sendSMS']);

    // Chat routes
    Route::get('/chats', [ChatController::class, 'index']);
    Route::post('/chats', [ChatController::class, 'store']);
    Route::get('/chats/{id}', [ChatController::class, 'show']);
    Route::post('/chats/{id}/messages', [ChatController::class, 'sendMessage']);

    // Communication Portal routes
    Route::prefix('communication')->group(function () {
        // Message management routes (commented out - controller missing)
        // Route::get('/messages', [CommunicationMessageController::class, 'index']);
        // Route::post('/messages', [CommunicationMessageController::class, 'store']);
        // Route::get('/messages/{id}', [CommunicationMessageController::class, 'show']);
        // Route::put('/messages/{id}', [CommunicationMessageController::class, 'update']);
        // Route::delete('/messages/{id}', [CommunicationMessageController::class, 'destroy']);
        // Route::post('/messages/{id}/send', [CommunicationMessageController::class, 'send']);
        // Route::get('/messages/{id}/statistics', [CommunicationMessageController::class, 'statistics']);

        // Approval workflow routes (commented out - controller missing)
        // Route::prefix('approvals')->group(function () {
        //     Route::get('/pending', [CommunicationApprovalController::class, 'pendingApprovals']);
        //     Route::get('/history', [CommunicationApprovalController::class, 'approvalHistory']);
        //     Route::get('/{id}', [CommunicationApprovalController::class, 'show']);
        //     Route::post('/{id}/approve', [CommunicationApprovalController::class, 'approve']);
        //     Route::post('/{id}/reject', [CommunicationApprovalController::class, 'reject']);
        //     Route::post('/bulk-approve', [CommunicationApprovalController::class, 'bulkApprove']);
        //     Route::get('/statistics/overview', [CommunicationApprovalController::class, 'statistics']);
        // });

        // Analytics routes (admin only) - commented out - controller missing
        // Route::middleware('role:admin')->prefix('analytics')->group(function () {
        //     Route::get('/dashboard', [CommunicationAnalyticsController::class, 'dashboard']);
        //     Route::get('/messages', [CommunicationAnalyticsController::class, 'messageAnalytics']);
        //     Route::get('/channels', [CommunicationAnalyticsController::class, 'channelAnalytics']);
        //     Route::get('/delivery', [CommunicationAnalyticsController::class, 'deliveryAnalytics']);
        //     Route::get('/export', [CommunicationAnalyticsController::class, 'export']);
        // });
    });

    // Dashboard routes
    Route::prefix('dashboard')->group(function () {
        // Admin dashboard - comprehensive analytics
        Route::get('/admin', [DashboardController::class, 'adminDashboard'])
            ->middleware('role:admin');

        // Dean dashboard - department-specific analytics
        Route::get('/dean', [DashboardController::class, 'deanDashboard'])
            ->middleware('role:dean');

        // Staff dashboard - role-specific analytics (accessible by multiple roles)
        Route::get('/staff', [DashboardController::class, 'staffDashboard']);

        // Security dashboard - security-specific analytics
        Route::get('/security', [DashboardController::class, 'securityDashboard'])
            ->middleware('role:security');

        // Housemaster dashboard - house-specific analytics
        Route::get('/housemaster', [DashboardController::class, 'housemasterDashboard'])
            ->middleware('role:housemaster');

        // Common dashboard widgets (accessible by all authenticated users)
        Route::get('/widgets', [DashboardController::class, 'getWidgets']);
        
        // Paginated audit trail routes
        Route::get('/audit-trail', [DashboardController::class, 'getPaginatedAuditTrail']);
        Route::get('/dean-audit-trail', [DashboardController::class, 'getPaginatedDeanAuditTrail'])
            ->middleware('role:dean');
    });
});

