<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $sql = "ALTER TABLE exeat_notifications MODIFY COLUMN notification_type ENUM(
        'request_submitted',
        'approval_required',
        'approved',
        'rejected',
        'parent_consent_required',
        'parent_consent_approved',
        'parent_consent_rejected',
        'hostel_signout_required',
        'security_signout_required',
        'completed',
        'cancelled',
        'reminder',
        'stage_change',
        'emergency',
        'rejection'
    ) NOT NULL";
    
    DB::statement($sql);
    
    echo "Database enum updated successfully!\n";
    echo "Added missing notification types: stage_change, emergency, rejection\n";
    
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}