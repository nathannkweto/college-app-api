<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exam_papers', function (Blueprint $table) {
            $table->id('db_id');
            $table->uuid('public_id')->unique();
            $table->foreignId('exam_season_db_id')
                ->constrained('exam_seasons', 'db_id')
                ->cascadeOnDelete();
            // Direct links instead of a program_courses pivot (that table doesn't exist
            // in this rebuild) — a paper is scoped to one program's sitting of one course.
            $table->foreignId('program_db_id')
                ->constrained('programs', 'db_id')
                ->cascadeOnDelete();
            $table->foreignId('course_db_id')
                ->constrained('courses', 'db_id')
                ->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->unsignedInteger('duration_minutes');
            $table->string('location');
            $table->timestamps();

            // One paper per course, per program, per season — supports updateOrCreate.
            $table->unique(['exam_season_db_id', 'program_db_id', 'course_db_id'], 'exam_papers_season_program_course_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_papers');
    }
};
