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

    public function handle()
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        // 1. Calculate Grade
        $grade = GradingService::calculateGrade($this->mark);

        // 2. Find and Update Enrollment
        Enrollment::updateOrCreate(
            [
                'student_id' => $this->studentId,
                'program_course_id' => $this->programCourseId,
            ],
            [
                'semester' => $this->semester,
                'score' => $this->mark,
                'grade' => $grade,
            ]
        );
    }
}
