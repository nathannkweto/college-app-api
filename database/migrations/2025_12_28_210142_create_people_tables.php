<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Lecturers
        Schema::create('lecturers', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();


            $table->string('lecturer_id')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('title');
            $table->string('gender');
            $table->string('national_id');
            $table->date('dob');
            $table->string('address')->nullable();
            $table->string('phone');


            $table->foreignId('department_id')->constrained();

            $table->timestamps();
        });

        // 2. Students
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('student_id')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->enum('gender', ['M', 'F']);
            $table->date('enrollment_date')->nullable();
            $table->string('national_id')->nullable()->unique();
            $table->date('dob');
            $table->string('address')->nullable();
            $table->string('phone');

            // Academic State
            $table->foreignId('program_id')->constrained();

            // THE ENGINE: Tracks their progress regardless of calendar date
            $table->integer('current_semester_sequence')->default(1);

            $table->enum('status', ['active', 'inactive', 'graduated', 'suspended'])->default('active');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('students');
        Schema::dropIfExists('lecturers');
    }
};
