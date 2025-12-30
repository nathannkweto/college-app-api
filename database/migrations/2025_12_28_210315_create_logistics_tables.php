<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Semesters (Global Time)
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();

            $table->string('academic_year');
            $table->integer('semester_number');

            // State Machine Flags
            $table->boolean('is_active')->default(true);

            $table->date('start_date');
            $table->integer('length_weeks');

            $table->timestamps();
        });

        // 2. Timetable Entries
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();

            // Context
            $table->foreignId('semester_id')->constrained();
            $table->foreignId('program_id')->constrained();
            $table->foreignId('course_id')->constrained();
            $table->foreignId('lecturer_id')->constrained();

            // Details
            $table->enum('day', ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN']);
            $table->time('start_time');
            $table->time('end_time');
            $table->string('location')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('timetable_entries');
        Schema::dropIfExists('semesters');
    }
};
