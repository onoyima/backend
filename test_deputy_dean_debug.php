<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== DEPUTY DEAN APPROVAL DEBUG ===\n\n";

// 1. Check exeat requests in secretary_review status
$deputyDeanRequests = \App\Models\ExeatRequest::where('status', 'secretary_review')->get();
echo "1. Exeat requests in secretary_review status: " . $deputyDeanRequests->count() . "\n";

foreach ($deputyDeanRequests as $request) {
    echo "   - Exeat ID: {$request->id}, Student ID: {$request->student_id}, Status: {$request->status}\n";
}

// 2. Check available exeat roles
echo "\n2. Available exeat roles:\n";
$roles = \App\Models\ExeatRole::all();
foreach ($roles as $role) {
    echo "   - Role ID: {$role->id}, Name: {$role->name}\n";
}

// 3. Check staff with deputy_dean role
echo "\n3. Staff with deputy_dean role:\n";
$deputyDeanStaff = \App\Models\StaffExeatRole::with(['staff', 'role'])
    ->whereHas('role', function($q) {
        $q->where('name', 'deputy_dean');
    })->get();

foreach ($deputyDeanStaff as $assignment) {
    echo "   - Staff ID: {$assignment->staff_id}, Name: {$assignment->staff->fname} {$assignment->staff->lname}\n";
}

// 4. Check if there are any existing approvals for secretary_review
echo "\n4. Existing approvals for secretary_review:\n";
$approvals = \App\Models\ExeatApproval::where('role', 'deputy_dean')->get();
foreach ($approvals as $approval) {
    echo "   - Exeat ID: {$approval->exeat_request_id}, Staff ID: {$approval->staff_id}, Status: {$approval->status}\n";
}

echo "\n=== DEBUG COMPLETE ===\n";