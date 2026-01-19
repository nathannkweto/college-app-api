<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;
    public function up()
    {
        // 1. Semesters (Global Time)
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();

            $table->string('academic_year'); // e.g., "2025-2026"
            $table->integer('semester_number'); // e.g., 1 or 2

            $table->boolean('is_active')->default(true);

            $table->date('start_date');
            $table->integer('length_weeks');

            $table->timestamps();

            $table->unique(['academic_year', 'semester_number']);
        });

        // 2. Timetable Entries
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();

            // Context
            $table->foreignId('semester_id')->constrained();
            $table->foreignId('program_course_id')->constrained();
            $table->foreignId('program_id')->nullable()->constrained()->onDelete('cascade');

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
