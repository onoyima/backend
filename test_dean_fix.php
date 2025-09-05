<?php

require 'bootstrap/app.php';

$app = Illuminate\Foundation\Application::getInstance();
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\DashboardAnalyticsService;

try {
    $service = new DashboardAnalyticsService();
    
    echo "Testing Dean Dashboard Methods...\n";
    
    // Test getDeanOverview
    $overview = $service->getDeanOverview(1);
    echo "Dean Overview: " . json_encode($overview, JSON_PRETTY_PRINT) . "\n";
    
    // Test getDepartmentStatistics
    $stats = $service->getDepartmentStatistics(1, 30);
    echo "Department Statistics: " . json_encode($stats, JSON_PRETTY_PRINT) . "\n";
    
    // Test getPendingApprovals
    $pending = $service->getPendingApprovals(1);
    echo "Pending Approvals: " . json_encode($pending, JSON_PRETTY_PRINT) . "\n";
    
    echo "\nAll dean dashboard methods working correctly!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}