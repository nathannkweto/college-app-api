<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResultController extends Controller
{
    public function index(Request $request)
    {
        // 1. FIXED: Use 'profile' to match your other controllers
        $student = Auth::user()->profile;

        // Safety check
        if (!$student || !$student->program) {
            return response()->json(['data' => ['gpa' => 0.0, 'semesters' => []]]);
        }

        $programId = $student->program->id;

        // 2. Fetch History: Exam Results
        // We eager load 'course.programs' to access the pivot table for semester_sequence
        $examResults = $student->examResults()
            ->with(['course.programs' => function($q) use ($programId) {
                $q->where('program_id', $programId);
            }, 'semester'])
            ->get();

        // 3. Fetch Current State
        $activeSemester = Semester::where('is_active', true)->first();
        // Use the relationship we defined in the Student model earlier
        $currentCourses = $student->currentCourses()->get();

        // 4. Merge Logic
        $transcriptData = $examResults->map(function ($result) use ($programId) {

            // Try to find the sequence for this course in this program
            $pivot = $result->course->programs->where('id', $programId)->first()?->pivot;
            $seq = $pivot ? $pivot->semester_sequence : 0;

            // Calculate Title
            if ($seq > 0) {
                $year = ceil($seq / 2);
                $semNum = ($seq % 2 == 0) ? 2 : 1;
                $semName = "Year $year - Semester $semNum";
            } else {
                // Fallback if pivot data is missing
                $semName = $result->semester->name ?? 'Unknown Semester';
            }

            // Published Logic
            $isPublished = (bool)$result->is_published;

            return [
                'semester_sort' => $seq > 0 ? $seq : 0, // Sort key
                'semester_name' => $semName,
                'course_id'     => $result->course_id,
                'course_name'   => $result->course->name ?? 'Unknown Course',
                // IF published, show grade, ELSE show N/A
                'grade'         => $isPublished ? $result->grade : 'N/A',
                'points'        => $isPublished ? (float)$result->points : 0.0,
                'is_published'  => $isPublished,
            ];
        });

        // Append Current Courses (if active semester exists)
        if ($activeSemester) {
            $existingCourseIds = $transcriptData
                ->pluck('course_id')
                ->toArray();

            foreach ($currentCourses as $course) {
                // Only add if not already in the list (prevents duplicates)
                if (!in_array($course->id, $existingCourseIds)) {

                    // Fetch sequence for current course
                    $pivot = $course->programs->where('id', $programId)->first()?->pivot;
                    $seq = $pivot ? $pivot->semester_sequence : 999;

                    if ($seq < 999 && $seq > 0) {
                        $year = ceil($seq / 2);
                        $semNum = ($seq % 2 == 0) ? 2 : 1;
                        $semName = "Year $year - Semester $semNum";
                    } else {
                        $semName = $activeSemester->name;
                    }

                    $transcriptData->push([
                        'semester_sort' => $seq,
                        'semester_name' => $semName,
                        'course_id'     => $course->id,
                        'course_name'   => $course->name,
                        'grade'         => 'N/A', // Ongoing courses are N/A
                        'points'        => 0.0,
                        'is_published'  => false,
                    ]);
                }
            }
        }

        // 5. Calculate GPA (Only Published)
        $gpa = $this->calculateGPA($examResults->where('is_published', true));

        // 6. Group & Sort
        $groupedSemesters = $transcriptData
            ->sortBy('semester_sort') // Sort: Year 1 Sem 1 -> Year 1 Sem 2 ...
            ->groupBy('semester_name')
            ->map(function ($rows, $semesterName) {
                return [
                    'semester_name' => $semesterName,
                    'results' => $rows->map(function ($r) {
                        return [
                            'course_name' => $r['course_name'],
                            'grade'       => $r['grade'],
                            'points'      => $r['points'],
                        ];
                    })->values()
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'gpa' => $gpa,
                'semesters' => $groupedSemesters
            ]
        ]);
    }

    private function calculateGPA($publishedResults)
    {
        if ($publishedResults->isEmpty()) return 0.0;
        $validScores = $publishedResults->whereNotNull('points');
        if ($validScores->isEmpty()) return 0.0;
        return round($validScores->avg('points'), 2);
    }
}
