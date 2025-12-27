<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lecturer;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LecturerController extends Controller
{
    public function index(Request $request)
    {
        $query = Lecturer::with('department');

        if ($request->has('department_public_id')) {
            $deptId = Department::getIdFromPublicId($request->department_public_id);
            $query->where('department_id', $deptId);
        }

        return $query->paginate(15);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'department_public_id' => 'required|exists:departments,public_id',
            'gender' => 'required|in:M,F',
            'title' => 'required|string',
            'qualification' => 'required|string',
            'national_id' => 'required|string|unique:lecturers,national_id',
            'employment_date' => 'required|date',
        ]);

        return DB::transaction(function () use ($validated) {
            $deptId = Department::getIdFromPublicId($validated['department_public_id']);

            // 1. Create Profile (ID auto-generated via boot model)
            $lecturer = Lecturer::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'department_id' => $deptId,
                'gender' => $validated['gender'],
                'title' => $validated['title'],
                'qualification' => $validated['qualification'],
                'national_id' => $validated['national_id'],
                'employment_date' => $validated['employment_date'],
                'user_id' => 0,
            ]);

            // 2. Create User Login
            $tempPassword = Str::random(8);
            $user = User::create([
                'name' => $validated['title'] . ' ' . $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($tempPassword),
                'role' => 'lecturer',
                'profileable_id' => $lecturer->id,
                'profileable_type' => Lecturer::class,
            ]);

            $lecturer->update(['user_id' => $user->id]);

            return response()->json([
                'message' => 'Lecturer registered',
                'lecturer_id' => $lecturer->lecturer_id,
                'temp_password' => $tempPassword
            ], 201);
        });
    }
}
