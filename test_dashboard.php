<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $service = new App\Services\DashboardAnalyticsService();
    echo "Service created successfully\n";
    
    // Test chart methods
    echo "Testing getExeatTrendsChart...\n";
    $chart1 = $service->getExeatTrendsChart(30);
    echo "✓ getExeatTrendsChart works\n";
    
    echo "Testing getStatusDistributionChart...\n";
    $chart2 = $service->getStatusDistributionChart(30);
    echo "✓ getStatusDistributionChart works\n";
    
    echo "Testing getUserActivityChart...\n";
    $chart3 = $service->getUserActivityChart(30);
    echo "✓ getUserActivityChart works\n";
    
    echo "Testing getApprovalRatesChart...\n";
    $chart4 = $service->getApprovalRatesChart(30);
    echo "✓ getApprovalRatesChart works\n";
    
    echo "Testing getDebtTrendsChart...\n";
    $chart5 = $service->getDebtTrendsChart(30);
    echo "✓ getDebtTrendsChart works\n";
    
    echo "Testing getPaymentMethodsStats...\n";
    $chart6 = $service->getPaymentMethodsStats(30);
    echo "✓ getPaymentMethodsStats works\n";
    
    echo "Testing getDebtAgingAnalysis...\n";
    $chart7 = $service->getDebtAgingAnalysis();
    echo "✓ getDebtAgingAnalysis works\n";
    
    echo "Testing getTopDebtors...\n";
    $debtors = $service->getTopDebtors(10);
    echo "✓ getTopDebtors works\n";
    
    echo "Testing getMonthlyDebtSummary...\n";
    $monthly = $service->getMonthlyDebtSummary();
    echo "✓ getMonthlyDebtSummary works\n";
    
    echo "Testing getDebtClearanceStats...\n";
    $clearance = $service->getDebtClearanceStats(30);
    echo "✓ getDebtClearanceStats works\n";
    
    echo "Testing getRecentActivities...\n";
    $activities = $service->getRecentActivities(10);
    echo "✓ getRecentActivities works\n";
    
    echo "All chart methods tested successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}