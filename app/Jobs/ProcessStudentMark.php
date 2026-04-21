<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Enrollment;
use App\Services\GradingService;

class ProcessStudentMark implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $studentId;
    public $programCourseId;
    public $semester;
    public $mark;

    public function __construct($studentId, $programCourseId, $semester, $mark)
    {
        $this->studentId = $studentId;
        $this->programCourseId = $programCourseId;
        $this->semester = $semester;
        $this->mark = $mark;
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $grade = GradingService::calculateGrade((float) $this->mark);

        Enrollment::updateOrCreate(
            [
                'student_id' => (int) $this->studentId,
                'program_course_id' => (int) $this->programCourseId,
            ],
            [
                'semester' => (string) $this->semester,
                'score' => (float) $this->mark,
                'grade' => $grade,
            ]
        );
    }
}
