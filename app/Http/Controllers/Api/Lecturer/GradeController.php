<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\ExamResult;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GradeController extends Controller
{
    public function store(Request $request, string $coursePublicId)
    {
        $request->validate([
            'students' => 'required|array',
            'students.*.student_public_id' => 'required|uuid',
            'students.*.total_score' => 'required|numeric|min:0|max:100',
        ]);

        // 1. Resolve Context
        $course = Course::where('public_id', $coursePublicId)->firstOrFail();
        $activeSemester = Semester::where('is_active', true)->firstOrFail();

        // 2. Optimization: Get all student IDs at once to avoid N+1 queries
        $studentPublicIds = collect($request->students)->pluck('student_public_id');
        $students = Student::whereIn('public_id', $studentPublicIds)->get()->keyBy('public_id');

        // 3. Process Grades in Transaction
        DB::transaction(function () use ($request, $course, $activeSemester, $students) {
            foreach ($request->students as $item) {
                $student = $students->get($item['student_public_id']);

                if (!$student) continue;

                $score = $item['total_score'];
                $gradeDetails = $this->calculateGrade($score);

                // 4. Update or Create Result (Matching your migration columns)
                ExamResult::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'course_id'  => $course->id,
                        'semester_id' => $activeSemester->id,
                    ],
                    [
                        'score'        => $score,
                        'grade'        => $gradeDetails['grade'],
                        'mention'      => $gradeDetails['mention'],
                        // Removed 'status' as it's not in your migration
                        'is_passed'    => $gradeDetails['is_passed'],
                        'is_published' => false,
                    ]
                );
            }
        });

        return response()->json(['message' => 'Grades submitted successfully']);
    }

    /**
     * Updated Helper: Returns is_passed as a boolean
     */
    private function calculateGrade($score)
    {
        if ($score >= 80) return ['grade' => 'A', 'is_passed' => true,  'mention' => 'Excellent'];
        if ($score >= 70) return ['grade' => 'B', 'is_passed' => true,  'mention' => 'Very Good'];
        if ($score >= 60) return ['grade' => 'C', 'is_passed' => true,  'mention' => 'Good'];
        if ($score >= 50) return ['grade' => 'D', 'is_passed' => true,  'mention' => 'Satisfactory'];
        if ($score >= 45) return ['grade' => 'E', 'is_passed' => true,  'mention' => 'Sufficient'];

        // Fail condition
        return ['grade' => 'F', 'is_passed' => false, 'mention' => 'Fail'];
    }
}
