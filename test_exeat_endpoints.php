<?php

/**
 * Test script for exeat request edit endpoints
 * 
 * This script tests the consistency of the exeat request edit endpoints
 * for admin, dean, and staff roles.
 */

// Set your API base URL and authentication token
$baseUrl = 'http://localhost:8000/api'; // Change this to your actual API URL
$token = 'YOUR_AUTH_TOKEN'; // Replace with a valid authentication token

// Test data for editing an exeat request
$testData = [
    'reason' => 'Updated reason for exeat',
    'destination' => 'Updated destination',
    'departure_date' => '2023-10-15',
    'return_date' => '2023-10-20',
    'actual_return_date' => '2023-10-21',
    'status' => 'completed'
];

// Test exeat request ID
$exeatId = 1; // Replace with an actual exeat request ID

// Function to make API requests
function makeRequest($url, $method, $data = null, $token) {
    $curl = curl_init();
    
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ];
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    
    if ($data && ($method === 'PUT' || $method === 'POST')) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    curl_close($curl);
    
    if ($err) {
        return ["error" => "cURL Error: $err", "code" => $httpCode];
    } else {
        return ["response" => json_decode($response, true), "code" => $httpCode];
    }
}

// Test admin endpoint
echo "\nTesting Admin Exeat Edit Endpoint:\n";
$adminUrl = "$baseUrl/admin/exeat-requests/$exeatId";
$adminResult = makeRequest($adminUrl, 'PUT', $testData, $token);

if ($adminResult['code'] >= 200 && $adminResult['code'] < 300) {
    echo "✅ Admin endpoint successful (HTTP {$adminResult['code']})\n";
    echo "Response structure: " . json_encode(array_keys($adminResult['response'])) . "\n";
} else {
    echo "❌ Admin endpoint failed (HTTP {$adminResult['code']})\n";
    echo "Error: " . json_encode($adminResult['response']) . "\n";
}

// Test dean endpoint
echo "\nTesting Dean Exeat Edit Endpoint:\n";
$deanUrl = "$baseUrl/dean/exeat-requests/$exeatId";
$deanResult = makeRequest($deanUrl, 'PUT', $testData, $token);

if ($deanResult['code'] >= 200 && $deanResult['code'] < 300) {
    echo "✅ Dean endpoint successful (HTTP {$deanResult['code']})\n";
    echo "Response structure: " . json_encode(array_keys($deanResult['response'])) . "\n";
} else {
    echo "❌ Dean endpoint failed (HTTP {$deanResult['code']})\n";
    echo "Error: " . json_encode($deanResult['response']) . "\n";
}

// Test staff endpoint
echo "\nTesting Staff Exeat Edit Endpoint:\n";
$staffUrl = "$baseUrl/staff/exeat-requests/$exeatId";
$staffResult = makeRequest($staffUrl, 'PUT', $testData, $token);

if ($staffResult['code'] >= 200 && $staffResult['code'] < 300) {
    echo "✅ Staff endpoint successful (HTTP {$staffResult['code']})\n";
    echo "Response structure: " . json_encode(array_keys($staffResult['response'])) . "\n";
} else {
    echo "❌ Staff endpoint failed (HTTP {$staffResult['code']})\n";
    echo "Error: " . json_encode($staffResult['response']) . "\n";
}

// Compare response structures
echo "\nComparing Response Structures:\n";

$adminKeys = isset($adminResult['response']) ? array_keys($adminResult['response']) : [];
$deanKeys = isset($deanResult['response']) ? array_keys($deanResult['response']) : [];
$staffKeys = isset($staffResult['response']) ? array_keys($staffResult['response']) : [];

$allMatch = true;

if ($adminKeys != $deanKeys) {
    echo "❌ Admin and Dean response structures differ\n";
    echo "Admin: " . json_encode($adminKeys) . "\n";
    echo "Dean: " . json_encode($deanKeys) . "\n";
    $allMatch = false;
}

if ($adminKeys != $staffKeys) {
    echo "❌ Admin and Staff response structures differ\n";
    echo "Admin: " . json_encode($adminKeys) . "\n";
    echo "Staff: " . json_encode($staffKeys) . "\n";
    $allMatch = false;
}

if ($deanKeys != $staffKeys) {
    echo "❌ Dean and Staff response structures differ\n";
    echo "Dean: " . json_encode($deanKeys) . "\n";
    echo "Staff: " . json_encode($staffKeys) . "\n";
    $allMatch = false;
}

if ($allMatch && !empty($adminKeys)) {
    echo "✅ All response structures match: " . json_encode($adminKeys) . "\n";
} elseif (empty($adminKeys) || empty($deanKeys) || empty($staffKeys)) {
    echo "⚠️ Could not compare response structures due to failed requests\n";
}

echo "\nTest completed.\n";