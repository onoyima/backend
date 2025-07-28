<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Staff;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminUserController extends Controller
{
    // GET /api/admin/users
    public function index()
    {
        $users = Staff::all(['id', 'fname', 'lname', 'email', 'status']);
        return response()->json(['users' => $users]);
    }

    // POST /api/admin/users
    public function store(Request $request)
    {
        $validated = $request->validate([
            'fname' => 'required|string',
            'lname' => 'required|string',
            'email' => 'required|email|unique:staff,email',
            'password' => 'required|string|min:6',
            'status' => 'nullable|string',
        ]);
        $user = Staff::create([
            'fname' => $validated['fname'],
            'lname' => $validated['lname'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'] ?? 'active',
        ]);
        Log::info('Admin created user', ['user_id' => $user->id]);
        return response()->json(['message' => 'User created.', 'user' => $user], 201);
    }

    // GET /api/admin/users/{id}
    public function show($id)
    {
        $user = Staff::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        return response()->json(['user' => $user]);
    }

    // PUT /api/admin/users/{id}
    public function update(Request $request, $id)
    {
        $user = Staff::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        $validated = $request->validate([
            'fname' => 'sometimes|required|string',
            'lname' => 'sometimes|required|string',
            'email' => 'sometimes|required|email|unique:staff,email,' . $id,
            'password' => 'nullable|string|min:6',
            'status' => 'nullable|string',
        ]);
        $user->fill($validated);
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();
        Log::info('Admin updated user', ['user_id' => $user->id]);
        return response()->json(['message' => 'User updated.', 'user' => $user]);
    }

    // DELETE /api/admin/users/{id}
    public function destroy($id)
    {
        $user = Staff::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        $user->delete();
        Log::info('Admin deleted user', ['user_id' => $id]);
        return response()->json(['message' => 'User deleted.']);
    }
}
