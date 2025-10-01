<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    // Create the analytics service first
    $analyticsService = new App\Services\DashboardAnalyticsService();
    echo "Analytics service created successfully\n";
    
    $controller = new App\Http\Controllers\DashboardController($analyticsService);
    echo "Controller created successfully\n";
    
    // Create a mock request
    $request = new Illuminate\Http\Request();
    
    echo "Testing adminDashboard method...\n";
    $response = $controller->adminDashboard($request);
    echo "✓ adminDashboard method works\n";
    
    // Get the response content
    $content = $response->getContent();
    $data = json_decode($content, true);
    
    if ($data) {
        echo "Response structure:\n";
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                echo "- $key: " . count($value) . " items\n";
            } else {
                echo "- $key: $value\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}