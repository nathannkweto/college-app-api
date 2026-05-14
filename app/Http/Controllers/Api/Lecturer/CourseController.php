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
        $isOddSemester = ($activeSemester->semester_number % 2) !== 0;

        // 3. Fetch Assignments
        $assignedCourses = ProgramCourse::query()
            ->with([
                'course',
                'program.department',
                'program.qualification'
            ])
            ->where('lecturer_id', $lecturer->id)
            ->whereRaw('semester_sequence % 2 = ?', [$isOddSemester ? 1 : 0])
            ->get();

        // 4. Transform for API
        $data = $assignedCourses->map(function ($assignment) {
            return [
                'public_id' => $assignment->course->public_id,
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

    $activeSemester = Semester::where('is_active', true)->first();
    if (!$activeSemester) abort(404, 'No active semester');

    // 1. Find the specific assignment
    $assignment = ProgramCourse::query()
        ->where('lecturer_id', $lecturer->id)
        ->whereHas('course', function($q) use ($coursePublicId) {
            $q->where('public_id', $coursePublicId);
        })
        ->with(['course', 'program'])
        ->firstOrFail();

    // 2. Fetch Students based on ENROLLMENT (The Source of Truth)
    $students = Student::query()
        ->whereHas('enrollments', function($q) use ($assignment) {
            // We only care if they have a record for THIS specific class assignment
            $q->where('program_course_id', $assignment->id);
        })
        ->with(['enrollments' => function($q) use ($assignment) {
            // Eager load the specific enrollment so we can see the grade/status
            $q->where('program_course_id', $assignment->id);
        }])
        ->orderBy('last_name')
        ->get();

    return response()->json([
        'program_course_id' => $assignment->id,
        'course' => [
            'name' => $assignment->course->name,
            'code' => $assignment->course->code,
        ],
        'program' => [
            'name' => $assignment->program->name,
            'code' => $assignment->program->code,
        ],
        'context' => [
            'semester' => $activeSemester->name,
            'semester_sequence' => $assignment->semester_sequence,
            'student_count' => $students->count(),
        ],
        'students' => $students->map(function($student) {
            $enrollment = $student->enrollments->first();

            return [
                'public_id' => $student->public_id,
                'student_id' => $student->student_id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'email' => $student->email,
                'avatar' => $student->avatar_url,
                
                // Now shows the actual grade, or 'Pending' if not yet graded
                'current_grade' => $enrollment ? $enrollment->grade : 'N/A',
                'current_status' => $enrollment ? $enrollment->grade : 'NOT ENROLLED',
            ];
        })
    ]);
}
}
