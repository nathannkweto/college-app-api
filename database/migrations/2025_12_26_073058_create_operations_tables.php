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
        // 1. Timetable Entries
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->enum('day', ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN']);
            $table->time('start_time');
            $table->time('end_time');
            $table->string('location'); // Room 101

            $table->foreignId('course_id')->constrained();
            $table->foreignId('lecturer_id')->constrained();
            $table->foreignId('student_group_id')->constrained('student_groups');

            $table->timestamps();

            // Indexes for performance & validation
            // Prevent Room Double Booking
            $table->index(['day', 'start_time', 'location']);
            // Prevent Lecturer Double Booking
            $table->index(['day', 'start_time', 'lecturer_id']);
        });

        // 2. Enrollments (Grades & Transcript)
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('course_id')->constrained();
            $table->foreignId('semester_id')->constrained(); // Calendar Semester

            $table->boolean('is_complete')->default(false);
            $table->decimal('grade', 5, 2)->nullable(); // Store final score
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operations_tables');
    }
};
