<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\ProgramCourse;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    /**
     * List all courses assigned to the logged-in lecturer
     * for the CURRENT semester (based on parity).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $lecturer = $user->profile()->with('department')->first();

        if (!$lecturer) {
            return response()->json(['message' => 'Lecturer profile not found.'], 404);
        }
        // 1. Get the Active Semester
        $activeSemester = Semester::where('is_active', true)->first();

        if (!$activeSemester) {
            return response()->json(['message' => 'No active semester found'], 404);
        }

        // 2. Determine Active Sequences (Odd vs Even)
        // If semester_number is 1, we want 1, 3, 5, 7...
        // If semester_number is 2, we want 2, 4, 6, 8...
        $isOddSemester = ($activeSemester->semester_number % 2) !== 0;

        // 3. Fetch Assignments
        // We query the Pivot table (ProgramCourse) directly to get the link
        // between Program and Course where this lecturer is assigned.
        $assignedCourses = ProgramCourse::query()
            ->with([
                'course',
                'program.department',
                'program.qualification'
            ])
            ->where('lecturer_id', $lecturer->id)
            // Filter by sequence parity (Modulus 2 logic)
            ->whereRaw('semester_sequence % 2 = ?', [$isOddSemester ? 1 : 0])
            ->get();

        // 4. Transform for API
        $data = $assignedCourses->map(function ($assignment) {
            return [
                'public_id' => $assignment->course->public_id, // Use Course Public ID for navigation
                'course_name' => $assignment->course->name,
                'course_code' => $assignment->course->code,
                'program_name' => $assignment->program->name,
                'program_code' => $assignment->program->code,
                'semester_sequence' => $assignment->semester_sequence,
                'program_public_id' => $assignment->program->public_id,
            ];
        });

        return response()->json([
            'meta' => [
                'semester' => $activeSemester->name,
                'type' => $isOddSemester ? 'Odd (1, 3, 5...)' : 'Even (2, 4, 6...)'
            ],
            'data' => $data
        ]);
    }

    /**
     * Get details of a specific course assignment,
     * including the list of Students currently in that class.
     */
    public function show(Request $request, string $coursePublicId)
    {
        $user = Auth::user();
        $lecturer = $user->profile()->with('department')->first();

        if (!$lecturer) {
            return response()->json(['message' => 'Lecturer profile not found.'], 404);
        }
        // 1. Validate Semester
        $activeSemester = Semester::where('is_active', true)->first();
        if (!$activeSemester) abort(404, 'No active semester');

        // 2. Find the specific assignment (ProgramCourse pivot)
        // We need to join the courses table to find by public_id
        $assignment = ProgramCourse::query()
            ->where('lecturer_id', $lecturer->id)
            ->whereHas('course', function($q) use ($coursePublicId) {
                $q->where('public_id', $coursePublicId);
            })
            ->with(['course', 'program'])
            ->firstOrFail();

        // 3. Fetch Students
        // We need students who belong to this Program
        // AND are currently at this specific Semester Sequence.
        $students = Student::query()
            ->where('program_id', $assignment->program_id)
            ->where('current_semester_sequence', $assignment->semester_sequence)
            // Optional: Filter by status (e.g., only 'Active' students)
            ->orderBy('last_name')
            ->get();

        return response()->json([
            'course' => [
                'name' => $assignment->course->name,
                'code' => $assignment->course->code,
                'description' => 'Course description placeholder...',
            ],
            'program' => [
                'name' => $assignment->program->name,
                'code' => $assignment->program->code,
            ],
            'context' => [
                'semester_sequence' => $assignment->semester_sequence,
                'student_count' => $students->count(),
            ],
            'students' => $students->map(function($student) {
                return [
                    'public_id' => $student->public_id,
                    'student_id' => $student->student_id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'email' => $student->email,
                    'avatar' => $student->avatar_url, // If exists
                ];
            })
        ]);
    }
}
