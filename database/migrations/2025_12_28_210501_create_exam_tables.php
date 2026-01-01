<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;
    public function up()
    {
        // 1. Exam Season
        Schema::create('exam_seasons', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->foreignId('semester_id')->constrained();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Exam Paper (The Schedule)
        Schema::create('exam_papers', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();

            $table->foreignId('exam_season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_course_id')->constrained('program_course')->cascadeOnDelete();

            $table->date('date');
            $table->time('start_time');
            $table->integer('duration_minutes');
            $table->string('location');

            $table->timestamps();
            $table->unique(['exam_season_id', 'program_course_id']);
        });

        // 3. Exam Results (The Grades)
        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')->constrained();
            $table->foreignId('course_id')->constrained();
            $table->foreignId('semester_id')->constrained();

            $table->decimal('score', 5, 2);
            $table->string('grade');
            $table->string('mention');
            $table->boolean('is_passed');

            $table->boolean('is_published')->default(false);

            $table->timestamps();

            $table->unique(['student_id', 'course_id', 'semester_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('exam_papers');
        Schema::dropIfExists('exam_seasons');
    }
};
