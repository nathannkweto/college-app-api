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
        // 1. Programs
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('code', 10)->unique(); // ICT
            $table->foreignId('qualification_id')->constrained();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Student Groups (Renamed from 'Group')
        Schema::create('student_groups', function (Blueprint $table) {
            $table->id();
            $table->string('letter', 1); // A, B, C
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            // Prevent duplicate "Group A" for the same program
            $table->unique(['program_id', 'letter']);
            $table->timestamps();
        });

        // 3. Courses
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('code', 10)->unique(); // ICT101
            $table->foreignId('department_id')->constrained();
            $table->timestamps();
        });

        // 4. Program Courses (Pivot Table: Structure of the degree)
        Schema::create('program_course', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->integer('semester_sequence'); // 1, 2, ... 8 (The stage of the degree)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_programs_tables');
    }
};
