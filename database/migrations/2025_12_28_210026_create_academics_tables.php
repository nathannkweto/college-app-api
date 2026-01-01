<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;
    public function up()
    {
        // 1. Departments
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });

        // 2. Qualifications
        Schema::create('qualifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('code');
            $table->timestamps();
        });

        // 3. Programs (Degrees)
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('code')->unique();
            $table->integer('total_semesters')->default(8);

            // Relationships
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('qualification_id')->constrained()->cascadeOnDelete();

            $table->timestamps();
        });

        // 4. Courses (Modules)
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('department_id')->constrained();
            $table->timestamps();
        });

        // 5. PROGRAM_COURSE
        Schema::create('program_course', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            $table->foreignId('lecturer_id')
                ->nullable()
                ->constrained('lecturers')
                ->nullOnDelete();

            $table->integer('semester_sequence');

            $table->unique(['program_id', 'course_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('program_course');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('qualifications');
        Schema::dropIfExists('departments');
    }
};
