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
            $table->string('name');
            $table->foreignId('semester_id')->constrained();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Exam Paper (The Schedule)
        Schema::create('exam_papers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('exam_season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_course_id')->constrained('program_course')->cascadeOnDelete();

            $table->date('date');
            $table->time('start_time');
            $table->integer('duration_minutes');
            $table->string('location');

            $table->timestamps();
            $table->unique(['exam_season_id', 'program_course_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('exam_papers');
        Schema::dropIfExists('exam_seasons');
    }
};
