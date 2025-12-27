<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $query = Student::with('program');

        // Filter: Get all students in "B.Sc Computer Science"
        if ($request->has('program_public_id')) {
            $progId = Program::getIdFromPublicId($request->program_public_id);
            $query->where('program_id', $progId);
        }

        // Search: By Name or Student ID
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%");
            });
        }

        return $query->paginate(20);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'program_public_id' => 'required|exists:programs,public_id',
            'gender' => 'required|in:M,F',
            'national_id' => 'required|string|unique:students,national_id',
            'enrollment_date' => 'required|date',
        ]);

        return DB::transaction(function () use ($validated) {
            $programId = Program::getIdFromPublicId($validated['program_public_id']);

            // 1. Create Student Profile (ID auto-generates here via Model Boot)
            $student = Student::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'], // Stored for easy contact
                'program_id' => $programId,
                'gender' => $validated['gender'],
                'national_id' => $validated['national_id'],
                'enrollment_date' => $validated['enrollment_date'],
                'user_id' => 0, // Placeholder
            ]);

            // 2. Create User Login (Password = Generated Student ID)
            // This is a common pattern: Student ID is their initial password
            $initialPassword = $student->student_id;

            $user = User::create([
                'name' => $validated['first_name'] . ' ' . $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($initialPassword),
                'role' => 'student',
            ]);

            // 3. Link them
            $student->update(['user_id' => $user->id]);

            // Polymorphic link
            $user->profileable_id = $student->id;
            $user->profileable_type = Student::class;
            $user->save();

            return response()->json([
                'message' => 'Student registered successfully',
                'student_id' => $student->student_id,
                'initial_password' => $initialPassword
            ], 201);
        });
    }

    public function show($publicId)
    {
        // Retrieve single student with their academic history
        return Student::with(['program', 'enrollments.course', 'enrollments.semester'])
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
