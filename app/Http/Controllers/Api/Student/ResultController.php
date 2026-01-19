<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\ResultPublication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResultController extends Controller
{
    public function index(Request $request)
    {
        // 1. Resolve Student Profile
        $student = Auth::user()->profile; // Assumes relation user -> hasOne profile (Student model)

        if (!$student) {
            return response()->json(['data' => ['semesters' => []]]);
        }

        // 2. Fetch all Enrollments for this student
        $enrollments = Enrollment::where('student_id', $student->id)
            ->with(['semester', 'programCourse.course'])
            ->get();

        // 3. Fetch Published Semesters for THIS Student's Program
        // We strictly filter by the student's program_id to ensure they don't see
        // results meant for other departments.
        $publishedSemesterIds = ResultPublication::where('program_id', $student->program_id)
            ->where('is_published', true)
            ->pluck('semester_id')
            ->toArray();

        // 4. Transform & Process Data
        $transcriptData = $enrollments->map(function ($enrollment) use ($publishedSemesterIds) {

            // A. Determine Visibility
            // True only if the publication record exists for (Student's Program + This Semester)
            $isPublished = in_array($enrollment->semester_id, $publishedSemesterIds);

            // B. Resolve Course Info
            $pCourse = $enrollment->programCourse;
            $course = $pCourse ? $pCourse->course : null;
            $courseName = $course ? $course->name : 'Unknown Course';

            // C. Calculate "Year X - Semester Y" Label based on Sequence
            // Note: This relies on your curriculum sequence (1 = Year 1 Sem 1, etc.)
            $seq = $pCourse ? $pCourse->semester_sequence : 0;

            if ($seq > 0) {
                $year = ceil($seq / 2);
                $semNum = ($seq % 2 == 0) ? 2 : 1;
                $semesterLabel = "Year $year - Semester $semNum";
            } else {
                $semesterLabel = $enrollment->semester->name ?? 'Unknown Semester';
            }

            return [
                'sort_sequence' => $seq > 0 ? $seq : 999,
                'semester_name' => $semesterLabel,
                'course_name'   => $courseName,

                // D. Mask Data if Not Published
                // If not published, we return 'Pending' or null to protect the data
                'grade'         => $isPublished ? $enrollment->grade : 'PENDING',
                'score'         => $isPublished ? $enrollment->score : null,
                'is_published'  => $isPublished,
            ];
        });

        // 5. Group by Semester & Sort
        $groupedSemesters = $transcriptData
            ->sortBy('sort_sequence') // Chronological order (Year 1 Sem 1 -> Year 1 Sem 2...)
            ->groupBy('semester_name')
            ->map(function ($rows, $semesterName) {
                return [
                    'semester_name' => $semesterName,
                    'results'       => $rows->map(function ($r) {
                        return [
                            'course_name'  => $r['course_name'],
                            'grade'        => $r['grade'],
                            'score'        => $r['score'],
                            'is_published' => $r['is_published'],
                        ];
                    })->values()
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'semesters' => $groupedSemesters
            ]
        ]);
    }
}
