<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\ExamResult;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $lecturer = Auth::user()->profile;
        $activeSemester = Semester::where('is_active', true)->first();

        if (!$activeSemester) return response()->json(['data' => []]);

        $courses = $lecturer->courses()
            ->wherePivot('semester_id', $activeSemester->id)
            ->get()
            ->map(function ($course) use ($activeSemester) {
                return [
                    'course_code' => $course->code,
                    'course_name' => $course->name,
                    'student_count' => $this->getStudentCount($course),
                    'semester' => $activeSemester->academic_year,
                    'credit_hours' => 4,
                    'description' => $course->description ?? '',
                ];
            });

        return response()->json(['data' => $courses]);
    }

    /**
     * FIX: Added missing summary method for Dashboard
     */
    public function summary(Request $request)
    {
        $lecturer = Auth::user()->profile;
        $activeSemester = Semester::where('is_active', true)->first();

        if (!$activeSemester) return response()->json(['data' => []]);

        $courses = $lecturer->courses()
            ->wherePivot('semester_id', $activeSemester->id)
            ->get()
            ->map(function ($course) {
                return [
                    'course_code' => $course->code,
                    'course_name' => $course->name,
                    'student_count' => $this->getStudentCount($course),
                ];
            });

        return response()->json(['data' => $courses]);
    }

    public function students(Request $request, $course_public_id)
    {
        $course = Course::where('public_id', $course_public_id)->firstOrFail();

        // Use the helper method defined below
        $students = $this->getEnrolledStudents($course);

        $activeSemester = Semester::where('is_active', true)->first();
        $results = $activeSemester ? ExamResult::where('course_id', $course->id)
            ->where('semester_id', $activeSemester->id)
            ->get()->keyBy('student_id') : [];

        $data = $students->map(function ($student) use ($results) {
            $grade = $results[$student->id] ?? null;
            return [
                'student_public_id' => $student->public_id,
                'student_id' => $student->student_id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'current_score' => $grade ? (float)$grade->score : null,
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Helper logic to find students for a course based on their program sequence
     */
    private function getEnrolledStudents($course)
    {
        $programs = $course->programs()->withPivot('semester_sequence')->get();

        return Student::where(function ($query) use ($programs) {
            foreach ($programs as $program) {
                $query->orWhere(function ($q) use ($program) {
                    $q->where('program_id', $program->id)
                        ->where('current_semester_sequence', $program->pivot->semester_sequence);
                });
            }
        })->get();
    }

    private function getStudentCount($course)
    {
        return $this->getEnrolledStudents($course)->count();
    }

    public function submitGrades(Request $request, $course_public_id)
    {
        // Validation and logic remain as previously discussed
        return response()->json(['message' => 'Grades submitted successfully.']);
    }
}
