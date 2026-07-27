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
        Schema::create('results', function (Blueprint $table) {
            $table->id('db_id');
            $table->uuid('public_id')->unique();
            $table->foreignId('semester_db_id')
                ->constrained('semesters', 'db_id')
                ->cascadeOnDelete();
            $table->foreignId('student_db_id')
                ->constrained('students', 'db_id')
                ->cascadeOnDelete();
            $table->foreignId('course_db_id')
                ->constrained('courses', 'db_id')
                ->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->string('grade');
            $table->decimal('pass_mark', 5, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
