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
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id('db_id');
            $table->uuid('public_id')->unique();
            $table->foreignId('semester_db_id')
                ->constrained('semesters', 'db_id')
                ->cascadeOnDelete();
            $table->foreignId('program_db_id')
                ->nullable()
                ->constrained('programs', 'db_id')
                ->cascadeOnDelete();
            $table->foreignId('course_db_id')
                ->constrained('courses', 'db_id')
                ->cascadeOnDelete();
            $table->foreignId('lecturer_db_id')
                ->nullable()
                ->constrained('lecturers', 'db_id')
                ->cascadeOnDelete();
            $table->string('day');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('location')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
    }
};
