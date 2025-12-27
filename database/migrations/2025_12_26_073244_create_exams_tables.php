<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Exam Season
        Schema::create('exam_seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // May 2025 Finals
            $table->foreignId('semester_id')->constrained();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        // 2. Exam Schedule (The physical paper)
        Schema::create('exam_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_season_id')->constrained();
            $table->foreignId('course_id')->constrained();

            $table->date('date');
            $table->time('start_time');
            $table->integer('duration_minutes');
            $table->string('location');

            $table->timestamps();
        });

        // 3. Exam Groups (Physical Batches)
        Schema::create('exam_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('exam_schedule_id')->constrained('exam_schedules');

            // Range of anonymized numbers in this room
            $table->string('exam_number_start');
            $table->string('exam_number_end');
            $table->timestamps();
        });

        // 4. Exam Numbers (Anonymization)
        Schema::create('exam_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('exam_season_id')->constrained();

            $table->string('exam_number')->unique(); // EX-25-001
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exams_tables');
    }
};
