<?php

namespace App\Jobs;

use App\Models\Grading;
use App\Models\Result;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessStudentMark implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Default pass mark used until there's a per-course/program pass-mark
     * source to pull from instead of a flat institutional default.
     */
    private const DEFAULT_PASS_MARK = 50.00;

    public function __construct(
        public int $studentDbId,
        public int $courseDbId,
        public int $semesterDbId,
        public float $score,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        DB::transaction(function () {
            $grading = Grading::forScore($this->score);

            Result::updateOrCreate(
                [
                    'student_db_id' => $this->studentDbId,
                    'course_db_id' => $this->courseDbId,
                    'semester_db_id' => $this->semesterDbId,
                ],
                [
                    'score' => $this->score,
                    'grade' => $grading?->mention ?? 'Ungraded',
                    'pass_mark' => self::DEFAULT_PASS_MARK,
                ]
            );
        });
    }
}
