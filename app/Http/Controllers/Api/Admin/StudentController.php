<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Models\Program;
use App\Models\Level;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    /**
     * GET /api/v1/admin/students
     */
    public function index(Request $request)
    {
        $query = Student::with(['user', 'program', 'level']);

        // Optional filters
        if ($request->filled('program_public_id')) {
            $program = Program::where('public_id', $request->program_public_id)->first();
            if ($program) {
                $query->where('program_db_id', $program->db_id);
            }
        }

        if ($request->filled('level_public_id')) {
            $level = Level::where('public_id', $request->level_public_id)->first();
            if ($level) {
                $query->where('level_db_id', $level->db_id);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $students = $query->orderBy('last_name')->paginate(20);

        return response()->json($students);
    }

    /**
     * POST /api/v1/admin/students
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'          => 'required|string|max:255',
            'last_name'           => 'required|string|max:255',
            'national_id_number'  => 'required|string|max:255',
            'gender'              => 'nullable|in:M,F',
            'email'               => 'nullable|email|unique:users,email',
            'dob'                 => 'nullable|date',
            'address'             => 'nullable|string|max:255',
            'phone'               => 'required|string|max:255',
            'id'                  => 'required|string|unique:students,id', // Student ID e.g. 2025-BA-001
            'program_public_id'   => 'required|exists:programs,public_id',
            'level_public_id'     => 'required|exists:levels,public_id',
            'enrollment_date'     => 'required|date',
            'password'            => 'nullable|string|min:6',
        ]);

        $program = Program::where('public_id', $validated['program_public_id'])->firstOrFail();
        $level   = Level::where('public_id', $validated['level_public_id'])->firstOrFail();

        // Create User first
        $user = User::create([
            'public_id' => (string) Str::uuid(),
            'name'      => $validated['first_name'] . ' ' . $validated['last_name'],
            'email'     => $validated['email'] ?? strtolower($validated['id']) . '@student.matemcollege.com',
            'password'  => Hash::make($validated['password'] ?? 'password123'),
            'role'      => 'student',
        ]);

        // Create Student profile
        $student = Student::create([
            'public_id'           => (string) Str::uuid(),
            'first_name'          => $validated['first_name'],
            'last_name'           => $validated['last_name'],
            'national_id_number'  => $validated['national_id_number'],
            'gender'              => $validated['gender'] ?? null,
            'email'               => $validated['email'] ?? $user->email,
            'dob'                 => $validated['dob'] ?? null,
            'address'             => $validated['address'] ?? null,
            'phone'               => $validated['phone'],
            'id'                  => $validated['id'],
            'program_db_id'       => $program->db_id,
            'level_db_id'         => $level->db_id,
            'enrollment_date'     => $validated['enrollment_date'],
            'user_db_id'          => $user->db_id,
        ]);

        $student->load(['user', 'program', 'level']);

        return response()->json([
            'message' => 'Student created successfully',
            'data'    => $student
        ], 201);
    }

    /**
     * GET /api/v1/admin/students/{public_id}
     */
    public function show($publicId)
    {
        $student = Student::with(['user', 'program', 'level', 'results'])
            ->where('public_id', $publicId)
            ->firstOrFail();

        return response()->json([
            'data' => $student
        ]);
    }

    /**
     * PUT /api/v1/admin/students/{public_id}
     */
    public function update(Request $request, $publicId)
    {
        $student = Student::where('public_id', $publicId)->firstOrFail();

        $validated = $request->validate([
            'first_name'          => 'sometimes|string|max:255',
            'last_name'           => 'sometimes|string|max:255',
            'national_id_number'  => 'sometimes|string|max:255',
            'gender'              => 'nullable|in:M,F',
            'email'               => ['nullable', 'email', Rule::unique('users', 'email')->ignore($student->user_db_id, 'db_id')],
            'dob'                 => 'nullable|date',
            'address'             => 'nullable|string|max:255',
            'phone'               => 'sometimes|string|max:255',
            'program_public_id'   => 'sometimes|exists:programs,public_id',
            'level_public_id'     => 'sometimes|exists:levels,public_id',
        ]);

        if (isset($validated['program_public_id'])) {
            $program = Program::where('public_id', $validated['program_public_id'])->firstOrFail();
            $validated['program_db_id'] = $program->db_id;
            unset($validated['program_public_id']);
        }

        if (isset($validated['level_public_id'])) {
            $level = Level::where('public_id', $validated['level_public_id'])->firstOrFail();
            $validated['level_db_id'] = $level->db_id;
            unset($validated['level_public_id']);
        }

        $student->update($validated);

        // Also update user name/email if needed
        if (isset($validated['first_name']) || isset($validated['last_name']) || isset($validated['email'])) {
            $student->user->update([
                'name'  => ($validated['first_name'] ?? $student->first_name) . ' ' . ($validated['last_name'] ?? $student->last_name),
                'email' => $validated['email'] ?? $student->user->email,
            ]);
        }

        $student->load(['user', 'program', 'level']);

        return response()->json([
            'message' => 'Student updated successfully',
            'data'    => $student
        ]);
    }

    /**
     * DELETE /api/v1/admin/students/{public_id}
     */
    public function destroy($publicId)
    {
        $student = Student::where('public_id', $publicId)->firstOrFail();

        // Optional: also delete the related user
        // $student->user()->delete();

        $student->delete();

        return response()->json([
            'message' => 'Student deleted successfully'
        ]);
    }

    /**
     * POST /api/v1/admin/students/batch-upload
     * (Placeholder - implement CSV logic later)
     */
    public function batchUpload(Request $request)
    {
        return response()->json([
            'message' => 'Batch upload endpoint - implementation pending'
        ], 501);
    }

    /**
     * POST /api/v1/admin/students/promotion-preview
     */
    public function promotionPreview(Request $request)
    {
        return response()->json([
            'message' => 'Promotion preview endpoint - implementation pending'
        ], 501);
    }

    /**
     * POST /api/v1/admin/students/promote
     */
    public function promote(Request $request)
    {
        return response()->json([
            'message' => 'Promote endpoint - implementation pending'
        ], 501);
    }
}
