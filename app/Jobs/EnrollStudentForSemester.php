<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use App\Models\Student;
use App\Models\Enrollment;
use App\Services\AcademicProgressionService;

class EnrollStudentForSemester implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $studentId;
    public $semesterId;
    public $isStartOfYear;

    public function __construct($studentId, $semesterId, $isStartOfYear)
    {
        $this->studentId = $studentId;
        $this->semesterId = $semesterId;
        $this->isStartOfYear = $isStartOfYear;
    }

    public function handle(AcademicProgressionService $progressionService)
    {
        if ($this->batch()?->cancelled()) return;

        $student = Student::find($this->studentId);
        if (!$student || $student->status !== 'active') return;

        // 1. Calculate Logic
        $result = $progressionService->determineNextStep($student, $this->isStartOfYear);

        DB::transaction(function () use ($student, $result) {

            // 2. Update Student Sequence
            $student->update([
                'current_semester_sequence' => $result['new_sequence']
            ]);

            // 3. Create Enrollments
            foreach ($result['program_course_ids'] as $pcId) {
                // Use firstOrCreate to avoid duplicates if job runs twice
                Enrollment::firstOrCreate(
                    [
                        'student_id' => $student->id,
                        'program_course_id' => $pcId,
                        'semester_id' => $this->semesterId
                    ],
                    [
                        'score' => 0.00, // Default start score
                        'grade' => 'Pending' // Default status
                    ]
                );
            }
        });
    }
}
