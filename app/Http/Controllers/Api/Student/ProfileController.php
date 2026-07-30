<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $student = Auth::user()->profile()->with(['level', 'program.department'])->first();

        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        return response()->json([
            'data' => [
                'public_id' => $student->public_id,
                'student_id' => $student->id, // business ID, e.g. "2025-BA-001"
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'email' => $student->email,
                'phone' => $student->phone,
                'gender' => $student->gender,
                'dob' => $student->dob?->format('Y-m-d'),
                'address' => $student->address,
                'enrollment_date' => $student->enrollment_date?->format('Y-m-d'),
                'level' => $student->level ? [
                    'public_id' => $student->level->public_id,
                    'name' => $student->level->name,
                ] : null,
                'program' => $student->program ? [
                    'public_id' => $student->program->public_id,
                    'name' => $student->program->name,
                    'department' => $student->program->department?->name,
                ] : null,
            ],
        ]);
    }
}
