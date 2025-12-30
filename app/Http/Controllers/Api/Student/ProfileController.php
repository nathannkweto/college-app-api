<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Get the authenticated student's profile.
     * Route: GET /api/v1/student/profile
     */
    public function show(Request $request)
    {
        $user = Auth::user();
        $student = $user->profile()->with(['program.department'])->first();

        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        return response()->json([
            'data' => [ // This matches the YAML wrapper
                'student_id'       => $student->student_id,
                'first_name'       => $student->first_name,
                'last_name'        => $student->last_name,
                'email'            => $user->email,
                'program_name'     => $student->program->name ?? 'N/A',
                'current_semester' => (int) $student->current_semester_sequence,
                'avatar_url'       => null, // Add this if you have it
            ]
        ]);
    }
}
