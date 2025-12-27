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
        // 1. Users (Auth) - Standard Laravel Table
        // Ensure you modify the default user migration or create a new one
        Schema::table('users', function (Blueprint $table) {
            // Adding fields to default users table if needed
            // The default usually has id, name, email, password
        });

        // 2. Admins
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        // 3. Lecturers
        Schema::create('lecturers', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained(); // Linked to Dept Code logic

            $table->string('first_name');
            $table->string('last_name');
            $table->string('title'); // Dr, Mr
            $table->enum('gender', ['M', 'F']);
            $table->string('qualification');
            $table->string('national_id')->unique(); // Sensitive
            $table->string('lecturer_id')->unique(); // MAT-2503 (Generated)

            $table->date('employment_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Students
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained();
            $table->foreignId('student_group_id')->nullable()->constrained('student_groups'); // Group A

            $table->string('first_name');
            $table->string('last_name');
            $table->enum('gender', ['M', 'F']);
            $table->string('phone')->nullable();
            $table->string('national_id')->unique();
            $table->string('student_id')->unique(); // 25BSC032 (Generated)

            $table->date('enrollment_date');
            $table->integer('study_year')->default(1); // Year 1, 2, 3
            $table->enum('status', ['active', 'inactive', 'graduated'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('people_tables');
    }
};
