<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lecturer;
use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LecturerController extends Controller
{
    /**
     * GET /api/v1/admin/lecturers
     */
    public function index(Request $request)
    {
        $query = Lecturer::with(['user', 'department']);

        if ($request->filled('department_public_id')) {
            $department = Department::where('public_id', $request->department_public_id)->first();
            if ($department) {
                $query->where('department_db_id', $department->db_id);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        $lecturers = $query->orderBy('last_name')->paginate(20);

        return response()->json($lecturers);
    }

    /**
     * POST /api/v1/admin/lecturers
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'            => 'required|string|max:255',
            'last_name'             => 'required|string|max:255',
            'email'                 => 'required|email|unique:users,email|unique:lecturers,email',
            'gender'                => 'required|in:M,F',
            'title'                 => 'required|in:Mr,Ms,Mrs,Dr,Prof',
            'phone'                 => 'required|string|max:255',
            'id'                    => 'required|string|unique:lecturers,id',
            'department_public_id'  => 'required|exists:departments,public_id',
            'employment_date'       => 'required|date',
            'password'              => 'nullable|string|min:6',
        ]);

        $department = Department::where('public_id', $validated['department_public_id'])->firstOrFail();

        // Create User
        $user = User::create([
            'public_id' => (string) Str::uuid(),
            'name'      => $validated['first_name'] . ' ' . $validated['last_name'],
            'email'     => $validated['email'],
            'password'  => Hash::make($validated['password'] ?? 'password123'),
            'role'      => 'lecturer',
        ]);

        // Create Lecturer
        $lecturer = Lecturer::create([
            'public_id'         => (string) Str::uuid(),
            'first_name'        => $validated['first_name'],
            'last_name'         => $validated['last_name'],
            'email'             => $validated['email'],
            'gender'            => $validated['gender'],
            'title'             => $validated['title'],
            'phone'             => $validated['phone'],
            'id'                => $validated['id'],
            'department_db_id'  => $department->db_id,
            'employment_date'   => $validated['employment_date'],
            'user_db_id'        => $user->db_id,
        ]);

        $lecturer->load(['user', 'department']);

        return response()->json([
            'message' => 'Lecturer created successfully',
            'data'    => $lecturer
        ], 201);
    }

    /**
     * GET /api/v1/admin/lecturers/{public_id}
     */
    public function show($publicId)
    {
        $lecturer = Lecturer::with(['user', 'department'])
            ->where('public_id', $publicId)
            ->firstOrFail();

        return response()->json([
            'data' => $lecturer
        ]);
    }

    /**
     * PUT /api/v1/admin/lecturers/{public_id}
     */
    public function update(Request $request, $publicId)
    {
        $lecturer = Lecturer::where('public_id', $publicId)->firstOrFail();

        $validated = $request->validate([
            'first_name'            => 'sometimes|string|max:255',
            'last_name'             => 'sometimes|string|max:255',
            'email'                 => [
                'sometimes', 'email',
                Rule::unique('users', 'email')->ignore($lecturer->user_db_id, 'db_id'),
                Rule::unique('lecturers', 'email')->ignore($lecturer->db_id, 'db_id'),
            ],
            'gender'                => 'sometimes|in:M,F',
            'title'                 => 'sometimes|in:Mr,Ms,Mrs,Dr,Prof',
            'phone'                 => 'sometimes|string|max:255',
            'department_public_id'  => 'sometimes|exists:departments,public_id',
            'employment_date'       => 'sometimes|date',
        ]);

        if (isset($validated['department_public_id'])) {
            $department = Department::where('public_id', $validated['department_public_id'])->firstOrFail();
            $validated['department_db_id'] = $department->db_id;
            unset($validated['department_public_id']);
        }

        $lecturer->update($validated);

        // Sync user data
        if (isset($validated['first_name']) || isset($validated['last_name']) || isset($validated['email'])) {
            $lecturer->user->update([
                'name'  => ($validated['first_name'] ?? $lecturer->first_name) . ' ' . ($validated['last_name'] ?? $lecturer->last_name),
                'email' => $validated['email'] ?? $lecturer->user->email,
            ]);
        }

        $lecturer->load(['user', 'department']);

        return response()->json([
            'message' => 'Lecturer updated successfully',
            'data'    => $lecturer
        ]);
    }

    /**
     * DELETE /api/v1/admin/lecturers/{public_id}
     */
    public function destroy($publicId)
    {
        $lecturer = Lecturer::where('public_id', $publicId)->firstOrFail();
        $lecturer->delete();

        return response()->json([
            'message' => 'Lecturer deleted successfully'
        ]);
    }

    /**
     * POST /api/v1/admin/lecturers/batch-upload
     */
    public function batchUpload(Request $request)
    {
        return response()->json([
            'message' => 'Batch upload endpoint - implementation pending'
        ], 501);
    }
}
