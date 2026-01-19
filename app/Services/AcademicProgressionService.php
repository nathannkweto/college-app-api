<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Enrollment;
use App\Models\ProgramCourse;
use Illuminate\Support\Collection;

class AcademicProgressionService
{
    /**
     * Calculate the courses a student should take next and their new semester sequence.
     */
    public function determineNextStep(Student $student, bool $isStartOfAcademicYear): array
    {
        // 1. New Students (No history)
        // If they have 0 enrollments, they start at Sequence 1 regardless of the flag
        if ($student->enrollments()->count() === 0) {
            return [
                'new_sequence' => 1,
                'program_course_ids' => $this->getStandardCourses($student->program_id, 1)
            ];
        }

        // 2. Logic Split
        if ($isStartOfAcademicYear) {
            return $this->handleStartOfYear($student);
        } else {
            return $this->handleMidYear($student);
        }
    }

    /**
     * Logic for Start of Year (Sem 1, 3, 5...)
     * Checks pass/fail ratio of the previous year.
     */
    protected function handleStartOfYear(Student $student): array
    {
        $currentSeq = $student->current_semester_sequence;

        // Identify the previous academic year's sequences (e.g. if ending seq 2, we look at 1 & 2)
        // Formula: The year we just finished is ceil(current / 2).
        // Example: If current is 2, we finished Year 1 (Seq 1 & 2).
        $startSeq = $currentSeq - 1; // e.g., 1
        $endSeq = $currentSeq;       // e.g., 2

        // Fetch Enrollments for that specific year (the "Previous Two Semesters")
        // We look for enrollments linked to program_courses that belong to these sequences
        $yearEnrollments = Enrollment::where('student_id', $student->id)
            ->whereHas('programCourse', function ($q) use ($startSeq, $endSeq) {
                $q->whereBetween('semester_sequence', [$startSeq, $endSeq]);
            })->get();

        if ($yearEnrollments->isEmpty()) {
            // Fallback: If data is missing, assume progression
            return $this->prepareProgression($student, $currentSeq + 1);
        }

        $total = $yearEnrollments->count();
        $failures = $yearEnrollments->where('grade', 'F')->count();

        // FAIL CONDITION: If half or more are F
        if (($failures / $total) >= 0.5) {
            // REPEAT YEAR: Go back to the start of the year they just messed up
            $repeatSequence = $startSeq;

            // Get standard courses for that repeated sequence
            $courses = $this->getStandardCourses($student->program_id, $repeatSequence);

            return [
                'new_sequence' => $repeatSequence,
                'program_course_ids' => $courses
            ];
        }

        // PASS CONDITION: Move to next year (Current + 1)
        return $this->prepareProgression($student, $currentSeq + 1);
    }

    /**
     * Logic for Mid-Year (Sem 2, 4, 6...)
     * Simply moves to next sequence + carryovers.
     */
    protected function handleMidYear(Student $student): array
    {
        $nextSeq = $student->current_semester_sequence + 1;
        return $this->prepareProgression($student, $nextSeq);
    }

    /**
     * Helper to gather Standard Courses + Carryover Failures
     */
    protected function prepareProgression(Student $student, int $targetSequence): array
    {
        // 1. Get Standard Courses for the new sequence
        $standardIds = $this->getStandardCourses($student->program_id, $targetSequence);

        // 2. Get Failed Courses (Carryovers)
        // Logic: Find courses with 'F' that have NOT been passed subsequently.
        $failedIds = Enrollment::where('student_id', $student->id)
            ->where('grade', 'F')
            ->get()
            ->pluck('program_course_id')
            ->unique();

        // Filter out ones they eventually passed
        $finalCarryovers = [];
        foreach ($failedIds as $pcId) {
            $hasPassed = Enrollment::where('student_id', $student->id)
                ->where('program_course_id', $pcId)
                ->where('grade', '!=', 'F') // Assuming anything not F is a pass
                ->exists();

            if (!$hasPassed) {
                $finalCarryovers[] = $pcId;
            }
        }

        // Merge and Unique
        $allIds = array_unique(array_merge($standardIds, $finalCarryovers));

        return [
            'new_sequence' => $targetSequence,
            'program_course_ids' => array_values($allIds) // Re-index array
        ];
    }

    protected function getStandardCourses($programId, $sequence): array
    {
        return ProgramCourse::where('program_id', $programId)
            ->where('semester_sequence', $sequence)
            ->pluck('id')
            ->toArray();
    }
}
