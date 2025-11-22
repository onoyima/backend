<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Demo Routes - For Testing Consent Pages
|--------------------------------------------------------------------------
|
| These routes are for demonstrating the consent success and error pages.
| Remove these routes in production.
|
*/

// Demo route for consent success page (approved)
Route::get('/demo/consent/success/approved', function () {
    $studentData = [
        'name' => 'John Doe',
        'matric_no' => 'STU/2023/001',
        'destination' => 'Lagos, Nigeria',
        'departure_date' => '2024-01-15 14:00:00',
        'return_date' => '2024-01-17 18:00:00',
        'reason' => 'Family visit'
    ];
    
    return view('consent.success', [
        'type' => 'approved',
        'title' => 'Consent Approved',
        'message' => 'Thank you! You have successfully approved the exeat request.',
        'student' => $studentData
    ]);
});

// Demo route for consent success page (declined)
Route::get('/demo/consent/success/declined', function () {
    $studentData = [
        'name' => 'Jane Smith',
        'matric_no' => 'STU/2023/002',
        'destination' => 'Abuja, Nigeria',
        'departure_date' => '2024-01-20 10:00:00',
        'return_date' => '2024-01-22 16:00:00',
        'reason' => 'Medical appointment'
    ];
    
    return view('consent.success', [
        'type' => 'declined',
        'title' => 'Consent Declined',
        'message' => 'Thank you for your response. The exeat request has been declined.',
        'student' => $studentData
    ]);
});

// Demo route for consent success page (already responded)
Route::get('/demo/consent/success/already-approved', function () {
    $studentData = [
        'name' => 'Michael Johnson',
        'matric_no' => 'STU/2023/003',
        'destination' => 'Port Harcourt, Nigeria',
        'departure_date' => '2024-01-18 12:00:00',
        'return_date' => '2024-01-20 20:00:00',
        'reason' => 'Wedding ceremony'
    ];
    
    return view('consent.success', [
        'type' => 'already_approved',
        'title' => 'Already Approved',
        'message' => 'This request has already been approved. Thank you for your previous response.',
        'student' => $studentData
    ]);
});

// Demo route for consent error page (expired)
Route::get('/demo/consent/error/expired', function () {
    return view('consent.error', [
        'type' => 'expired',
        'title' => 'Consent Link Expired',
        'message' => 'This consent link has expired. Consent links are valid for 24 hours for security reasons.'
    ]);
});

// Demo route for consent error page (not found)
Route::get('/demo/consent/error/not-found', function () {
    return view('consent.error', [
        'type' => 'not_found',
        'title' => 'Consent Request Not Found',
        'message' => 'This consent link may be invalid, expired, or has been removed. If you believe this is an error, please contact the school administration.'
    ]);
});

// Demo route for consent error page (invalid action)
Route::get('/demo/consent/error/invalid-action', function () {
    return view('consent.error', [
        'type' => 'invalid_action',
        'title' => 'Invalid Action',
        'message' => 'The requested action is not valid. Please use the original link from your email.'
    ]);
});

// Demo index page with links to all consent page demos
Route::get('/demo/consent', function () {
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Consent Pages Demo</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
            h1 { color: #004f40; }
            .demo-section { margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
            .demo-links { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px; }
            .demo-link { display: block; padding: 10px 15px; background: #004f40; color: white; text-decoration: none; border-radius: 5px; text-align: center; }
            .demo-link:hover { background: #00695c; }
            .error-link { background: #f44336; }
            .error-link:hover { background: #d32f2f; }
        </style>
    </head>
    <body>
        <h1>Consent Pages Demo</h1>
        <p>Click the links below to preview the different consent response pages:</p>
        
        <div class="demo-section">
            <h2>Success Pages</h2>
            <div class="demo-links">
                <a href="/demo/consent/success/approved" class="demo-link">Consent Approved</a>
                <a href="/demo/consent/success/declined" class="demo-link">Consent Declined</a>
                <a href="/demo/consent/success/already-approved" class="demo-link">Already Responded</a>
            </div>
        </div>
        
        <div class="demo-section">
            <h2>Error Pages</h2>
            <div class="demo-links">
                <a href="/demo/consent/error/expired" class="demo-link error-link">Expired Link</a>
                <a href="/demo/consent/error/not-found" class="demo-link error-link">Link Not Found</a>
                <a href="/demo/consent/error/invalid-action" class="demo-link error-link">Invalid Action</a>
            </div>
        </div>
        
        <div class="demo-section">
            <h3>Features of the New Consent Pages:</h3>
            <ul>
                <li><strong>Professional Design:</strong> Modern, responsive layout with consistent branding</li>
                <li><strong>Clear Visual Feedback:</strong> Color-coded icons and messages for different scenarios</li>
                <li><strong>Student Information:</strong> Displays relevant exeat request details</li>
                <li><strong>User-Friendly Messages:</strong> Clear explanations and next steps</li>
                <li><strong>Mobile Responsive:</strong> Works perfectly on all device sizes</li>
                <li><strong>Security Focused:</strong> Explains why links expire and security measures</li>
            </ul>
        </div>
    </body>
    </html>
    ';
});