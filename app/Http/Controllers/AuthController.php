<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Try staff first
        $staff = Staff::where('email', $request->email)->first();
        if ($staff) {
            if (Hash::check($request->password, $staff->password)) {
                $token = $staff->createToken('api-token')->plainTextToken;
                // Fetch roles
                $roles = $staff->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();
                // Hardcode staff_id 596 as admin
                if (in_array($staff->id, [596, 2, 3]) && !in_array('admin', $roles)) {
                    $roles[] = 'admin';
                }
                Log::info('Login success', ['email' => $request->email, 'user_type' => 'staff', 'user_id' => $staff->id]);
                return response()->json([
                    'user' => $staff,
                    'role' => 'staff',
                    'roles' => $roles,
                    'token' => $token,
                    'message' => 'Login successful!'
                ]);
            } else {
                Log::warning('Login failed: Incorrect password', ['email' => $request->email, 'user_type' => 'staff']);
                return response()->json([
                    'message' => 'Incorrect password.'
                ], 401);
            }
        }

        // Try student
        $student = Student::where('email', $request->email)->first();
        if ($student) {
            if (Hash::check($request->password, $student->password)) {
                $token = $student->createToken('api-token')->plainTextToken;
                Log::info('Login success', ['email' => $request->email, 'user_type' => 'student', 'user_id' => $student->id]);
                return response()->json([
                    'user' => $student,
                    'role' => 'student',
                    'token' => $token,
                    'message' => 'Login successful!'
                ]);
            } else {
                Log::warning('Login failed: Incorrect password', ['email' => $request->email, 'user_type' => 'student']);
                return response()->json([
                    'message' => 'Incorrect password.'
                ], 401);
            }
        }

        Log::warning('Login failed: Email not found', ['email' => $request->email]);
        return response()->json([
            'message' => 'Email not found.'
        ], 404);
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            if ($user) {
                $user->currentAccessToken()->delete();
                Log::info('Logout success', ['user_id' => $user->id, 'user_type' => get_class($user)]);
                return response()->json([
                    'message' => 'Logout successful.'
                ]);
            } else {
                Log::warning('Logout failed: No authenticated user');
                return response()->json([
                    'message' => 'No authenticated user.'
                ], 401);
            }
        } catch (\Exception $e) {
            Log::error('Logout failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Logout failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
