<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;
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

            $table->foreignId('program_id')->constrained();

            $table->integer('current_semester_sequence')->default(1);

            $table->enum('status', ['active', 'inactive', 'graduated', 'suspended'])->default('active');

            $table->timestamps();
        });

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            // This links the Admin profile to the User login
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('admins');
        Schema::dropIfExists('students');
        Schema::dropIfExists('lecturers');
    }
};
