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
        Schema::create('program_courses', function (Blueprint $table) {
            $table->id('db_id');
            $table->uuid('public_id')->unique();

            $table->foreignId('program_db_id')
                ->constrained('programs', 'db_id')
                ->cascadeOnDelete();

            $table->foreignId('course_db_id')
                ->constrained('courses', 'db_id')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('semester_sequence'); // 1, 2, 3...

            $table->foreignId('lecturer_db_id')
                ->nullable()
                ->constrained('lecturers', 'db_id')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['program_db_id', 'course_db_id'], 'program_course_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_courses');
    }
};
