<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Student;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminStudentController extends Controller
{
    // GET /api/admin/students
    public function index()
    {
        $students = Student::all(['id', 'fname', 'lname', 'email', 'status']);
        return response()->json(['students' => $students]);
    }

    // POST /api/admin/students
    public function store(Request $request)
    {
        $validated = $request->validate([
            'fname' => 'required|string',
            'lname' => 'required|string',
            'email' => 'required|email|unique:students,email',
            'password' => 'required|string|min:6',
            'status' => 'nullable|string',
        ]);
        $student = Student::create([
            'fname' => $validated['fname'],
            'lname' => $validated['lname'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'] ?? 'active',
        ]);
        Log::info('Admin created student', ['student_id' => $student->id]);
        return response()->json(['message' => 'Student created.', 'student' => $student], 201);
    }

    // GET /api/admin/students/{id}
    public function show($id)
    {
        $student = Student::find($id);
        if (!$student) {
            return response()->json(['message' => 'Student not found.'], 404);
        }
        return response()->json(['student' => $student]);
    }

    // PUT /api/admin/students/{id}
    public function update(Request $request, $id)
    {
        $student = Student::find($id);
        if (!$student) {
            return response()->json(['message' => 'Student not found.'], 404);
        }
        $validated = $request->validate([
            'fname' => 'sometimes|required|string',
            'lname' => 'sometimes|required|string',
            'email' => 'sometimes|required|email|unique:students,email,' . $id,
            'password' => 'nullable|string|min:6',
            'status' => 'nullable|string',
        ]);
        $student->fill($validated);
        if (!empty($validated['password'])) {
            $student->password = Hash::make($validated['password']);
        }
        $student->save();
        Log::info('Admin updated student', ['student_id' => $student->id]);
        return response()->json(['message' => 'Student updated.', 'student' => $student]);
    }

    // DELETE /api/admin/students/{id}
    public function destroy($id)
    {
        $student = Student::find($id);
        if (!$student) {
            return response()->json(['message' => 'Student not found.'], 404);
        }
        $student->delete();
        Log::info('Admin deleted student', ['student_id' => $id]);
        return response()->json(['message' => 'Student deleted.']);
    }
}
