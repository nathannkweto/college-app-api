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
        // 1. Departments
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name'); // Mathematics
            $table->string('code', 5)->unique(); // MAT
            $table->timestamps();
        });

        // 2. Qualifications
        Schema::create('qualifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name'); // Bachelor of Science
            $table->string('code', 5); // BSC
            $table->timestamps();
        });

        // 3. Academic Years
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('year')->unique(); // "2024/25"
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        // 4. Semesters (Calendar Time)
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->enum('semester_number', [1, 2]); // Calendar semester 1 or 2
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('core_structure_tables');
    }
};
