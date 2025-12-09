#!/usr/bin/env php
<?php

/**
 * Fast-Track Diagnostic Script
 * 
 * This script helps diagnose why students aren't appearing in Fast-Track search.
 * 
 * Usage:
 *   php artisan tinker
 *   Then paste the code from this file
 * 
 * OR run directly:
 *   php fast-track-diagnostic.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ExeatRequest;
use App\Models\Student;

echo "\n=== FAST-TRACK DIAGNOSTIC TOOL ===\n\n";

// Test 1: Check for students with security_signout status
echo "Test 1: Checking for students with 'security_signout' status...\n";
$signoutCount = ExeatRequest::where('status', 'security_signout')->count();
echo "Found: {$signoutCount} exeat requests\n";

if ($signoutCount > 0) {
    $sample = ExeatRequest::where('status', 'security_signout')
        ->with('student')
        ->first();

    echo "\nSample Record:\n";
    echo "  Exeat Request ID: {$sample->id}\n";
    echo "  Status: {$sample->status}\n";
    echo "  Student ID: {$sample->student_id}\n";
    echo "  Student Name: " . ($sample->student ? $sample->student->fname . ' ' . $sample->student->lname : 'N/A') . "\n";
    echo "  Matric No (Request): {$sample->matric_no}\n";
    echo "  Matric No (Student): " . ($sample->student ? $sample->student->matric_no : 'N/A') . "\n";
    echo "  Updated At: {$sample->updated_at}\n";
}

echo "\n" . str_repeat("-", 50) . "\n\n";

// Test 2: Check for students with security_signin status
echo "Test 2: Checking for students with 'security_signin' status...\n";
$signinCount = ExeatRequest::where('status', 'security_signin')->count();
echo "Found: {$signinCount} exeat requests\n";

if ($signinCount > 0) {
    $sample = ExeatRequest::where('status', 'security_signin')
        ->with('student')
        ->first();

    echo "\nSample Record:\n";
    echo "  Exeat Request ID: {$sample->id}\n";
    echo "  Status: {$sample->status}\n";
    echo "  Student ID: {$sample->student_id}\n";
    echo "  Student Name: " . ($sample->student ? $sample->student->fname . ' ' . $sample->student->lname : 'N/A') . "\n";
    echo "  Matric No (Request): {$sample->matric_no}\n";
    echo "  Matric No (Student): " . ($sample->student ? $sample->student->matric_no : 'N/A') . "\n";
    echo "  Updated At: {$sample->updated_at}\n";
}

echo "\n" . str_repeat("-", 50) . "\n\n";

// Test 3: Check all unique statuses
echo "Test 3: All unique statuses in exeat_requests table:\n";
$statuses = ExeatRequest::select('status')
    ->distinct()
    ->pluck('status')
    ->toArray();

foreach ($statuses as $status) {
    $count = ExeatRequest::where('status', $status)->count();
    echo "  - {$status}: {$count} requests\n";
}

echo "\n" . str_repeat("-", 50) . "\n\n";

// Test 4: Search simulation
echo "Test 4: Simulating a search...\n";
echo "Enter a search term (name or matric): ";
$searchTerm = trim(fgets(STDIN));

if (!empty($searchTerm)) {
    echo "\nSearching for: '{$searchTerm}'\n\n";

    // Simulate the exact query from searchActionable
    $results = ExeatRequest::with(['student:id,fname,lname,mname,passport,matric_no', 'category:id,name'])
        ->where('status', 'like', '%security_signout%')
        ->where(function ($q) use ($searchTerm) {
            $q->whereHas('student', function ($sq) use ($searchTerm) {
                $sq->where('fname', 'like', "%{$searchTerm}%")
                    ->orWhere('lname', 'like', "%{$searchTerm}%")
                    ->orWhere('mname', 'like', "%{$searchTerm}%")
                    ->orWhere('matric_no', 'like', "%{$searchTerm}%");

                if (is_numeric($searchTerm)) {
                    $sq->orWhere('id', $searchTerm);
                }
            })
                ->orWhere('matric_no', 'like', "%{$searchTerm}%");

            if (is_numeric($searchTerm)) {
                $q->orWhere('id', $searchTerm);
            }
        })
        ->orderBy('updated_at', 'desc')
        ->take(50)
        ->get();

    echo "Strict Search Results: {$results->count()} found\n";

    if ($results->isNotEmpty()) {
        foreach ($results as $result) {
            echo "\n  - ID: {$result->id}\n";
            echo "    Student: " . ($result->student ? $result->student->fname . ' ' . $result->student->lname : 'N/A') . "\n";
            echo "    Matric: " . ($result->student ? $result->student->matric_no : 'N/A') . "\n";
            echo "    Status: {$result->status}\n";
        }
    } else {
        echo "\nNo results with strict search. Trying fallback...\n";

        // Fallback search
        $fallbackResults = ExeatRequest::with(['student:id,fname,lname,mname,passport,matric_no', 'category:id,name'])
            ->where(function ($q) use ($searchTerm) {
                $q->whereHas('student', function ($sq) use ($searchTerm) {
                    $sq->where('fname', 'like', "%{$searchTerm}%")
                        ->orWhere('lname', 'like', "%{$searchTerm}%")
                        ->orWhere('mname', 'like', "%{$searchTerm}%")
                        ->orWhere('matric_no', 'like', "%{$searchTerm}%");

                    if (is_numeric($searchTerm)) {
                        $sq->orWhere('id', $searchTerm);
                    }
                })
                    ->orWhere('matric_no', 'like', "%{$searchTerm}%");

                if (is_numeric($searchTerm)) {
                    $q->orWhere('id', $searchTerm);
                }
            })
            ->orderBy('updated_at', 'desc')
            ->take(10)
            ->get();

        echo "Fallback Search Results: {$fallbackResults->count()} found\n";

        if ($fallbackResults->isNotEmpty()) {
            foreach ($fallbackResults as $result) {
                echo "\n  - ID: {$result->id}\n";
                echo "    Student: " . ($result->student ? $result->student->fname . ' ' . $result->student->lname : 'N/A') . "\n";
                echo "    Matric: " . ($result->student ? $result->student->matric_no : 'N/A') . "\n";
                echo "    Status: {$result->status} ← THIS IS WHY IT'S NOT SHOWING\n";
            }
        } else {
            echo "\nNo results even in fallback search!\n";
            echo "Possible reasons:\n";
            echo "  1. Student doesn't exist\n";
            echo "  2. Name/matric is misspelled\n";
            echo "  3. Student record is missing (student_id doesn't match)\n";
        }
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Diagnostic complete!\n\n";
