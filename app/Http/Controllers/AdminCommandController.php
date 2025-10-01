<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class AdminCommandController extends Controller
{
    /**
     * Display the command execution interface
     */
    public function index()
    {
        return view('admin.commands.index');
    }

    /**
     * Execute the check-overdue command manually
     */
    public function checkOverdue(Request $request)
    {
        try {
            // Capture the command output
            $exitCode = Artisan::call('exeat:check-overdue');
            $output = Artisan::output();
            
            // Log the manual execution
            Log::info('Manual execution of exeat:check-overdue command', [
                'user_id' => 'unauthenticated',
                'exit_code' => $exitCode,
                'output' => $output
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Check overdue command executed successfully',
                    'output' => $output,
                    'exit_code' => $exitCode
                ]);
            }

            return redirect()->back()->with('success', 'Check overdue command executed successfully')
                                   ->with('command_output', $output);
                                   
        } catch (\Exception $e) {
            Log::error('Failed to execute exeat:check-overdue command', [
                'user_id' => 'unauthenticated',
                'error' => $e->getMessage()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to execute command: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to execute command: ' . $e->getMessage());
        }
    }

    /**
     * Execute the expire-overdue command manually
     */
    public function expireOverdue(Request $request)
    {
        try {
            // Capture the command output
            $exitCode = Artisan::call('exeat:expire-overdue');
            $output = Artisan::output();
            
            // Log the manual execution
            Log::info('Manual execution of exeat:expire-overdue command', [
                'user_id' => 'unauthenticated',
                'exit_code' => $exitCode,
                'output' => $output
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Expire overdue command executed successfully',
                    'output' => $output,
                    'exit_code' => $exitCode
                ]);
            }

            return redirect()->back()->with('success', 'Expire overdue command executed successfully')
                                   ->with('command_output', $output);
                                   
        } catch (\Exception $e) {
            Log::error('Failed to execute exeat:expire-overdue command', [
                'user_id' => 'unauthenticated',
                'error' => $e->getMessage()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to execute command: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to execute command: ' . $e->getMessage());
        }
    }

    /**
     * Execute both commands in sequence
     */
    public function runAll(Request $request)
    {
        try {
            $results = [];
            
            // Execute check-overdue first
            $exitCode1 = Artisan::call('exeat:check-overdue');
            $output1 = Artisan::output();
            $results['check_overdue'] = [
                'exit_code' => $exitCode1,
                'output' => $output1
            ];
            
            // Execute expire-overdue second
            $exitCode2 = Artisan::call('exeat:expire-overdue');
            $output2 = Artisan::output();
            $results['expire_overdue'] = [
                'exit_code' => $exitCode2,
                'output' => $output2
            ];
            
            // Log the manual execution
            Log::info('Manual execution of all exeat commands', [
                'user_id' => 'unauthenticated',
                'results' => $results
            ]);

            $combinedOutput = "=== Check Overdue Results ===\n" . $output1 . 
                            "\n\n=== Expire Overdue Results ===\n" . $output2;

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'All commands executed successfully',
                    'results' => $results,
                    'combined_output' => $combinedOutput
                ]);
            }

            return redirect()->back()->with('success', 'All commands executed successfully')
                                   ->with('command_output', $combinedOutput);
                                   
        } catch (\Exception $e) {
            Log::error('Failed to execute all exeat commands', [
                'user_id' => 'unauthenticated',
                'error' => $e->getMessage()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to execute commands: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to execute commands: ' . $e->getMessage());
        }
    }
}