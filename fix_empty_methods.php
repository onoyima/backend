<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ExeatApproval;
use App\Models\ExeatRequest;

echo "=== CHECKING FOR EMPTY METHOD FIELDS ===\n\n";

// Find records with empty method fields
$emptyMethods = ExeatApproval::whereNull('method')
    ->orWhere('method', '')
    ->get();

echo "Found " . $emptyMethods->count() . " records with empty method fields:\n\n";

foreach ($emptyMethods as $approval) {
    echo "ID: {$approval->id}, Exeat: {$approval->exeat_request_id}, Staff: {$approval->staff_id}, Role: {$approval->role}, Created: {$approval->created_at}\n";
    
    // Try to determine what the method should be based on the role and timing
    $exeatRequest = ExeatRequest::find($approval->exeat_request_id);
    if ($exeatRequest) {
        echo "  Current exeat status: {$exeatRequest->status}\n";
        
        // For security role, we need to determine if this was signout or signin
        if ($approval->role === 'security') {
            // Check if there are other security approvals for this exeat
            $otherSecurityApprovals = ExeatApproval::where('exeat_request_id', $approval->exeat_request_id)
                ->where('role', 'security')
                ->where('id', '!=', $approval->id)
                ->whereNotNull('method')
                ->where('method', '!=', '')
                ->get();
                
            if ($otherSecurityApprovals->count() > 0) {
                echo "  Other security approvals found:\n";
                foreach ($otherSecurityApprovals as $other) {
                    echo "    - Method: {$other->method}, Created: {$other->created_at}\n";
                }
                
                // If there's already a security_signout, this should be security_signin
                $hasSignout = $otherSecurityApprovals->where('method', 'security_signout')->count() > 0;
                $hasSignin = $otherSecurityApprovals->where('method', 'security_signin')->count() > 0;
                
                if ($hasSignout && !$hasSignin) {
                    echo "  Suggested method: security_signin\n";
                } elseif (!$hasSignout) {
                    echo "  Suggested method: security_signout\n";
                } else {
                    echo "  Cannot determine method - both signout and signin exist\n";
                }
            } else {
                echo "  No other security approvals found\n";
                echo "  Suggested method: security_signout (first security action)\n";
            }
        }
    }
    echo "\n";
}

if ($emptyMethods->count() > 0) {
    echo "\n=== FIXING EMPTY METHOD FIELDS ===\n\n";
    
    foreach ($emptyMethods as $approval) {
        if ($approval->role === 'security') {
            // Check if there are other security approvals for this exeat
            $otherSecurityApprovals = ExeatApproval::where('exeat_request_id', $approval->exeat_request_id)
                ->where('role', 'security')
                ->where('id', '!=', $approval->id)
                ->whereNotNull('method')
                ->where('method', '!=', '')
                ->get();
                
            $hasSignout = $otherSecurityApprovals->where('method', 'security_signout')->count() > 0;
            
            if ($hasSignout) {
                // If there's already a signout, this should be signin
                $approval->method = 'security_signin';
                echo "Setting approval ID {$approval->id} method to: security_signin\n";
            } else {
                // This should be the signout
                $approval->method = 'security_signout';
                echo "Setting approval ID {$approval->id} method to: security_signout\n";
            }
            
            $approval->save();
        } else {
            echo "Skipping non-security approval ID {$approval->id} (role: {$approval->role})\n";
        }
    }
    
    echo "\n=== FIX COMPLETED ===\n";
} else {
    echo "No empty method fields found.\n";
}