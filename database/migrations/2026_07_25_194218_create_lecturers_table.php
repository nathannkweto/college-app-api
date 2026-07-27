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
        Schema::create('lecturers', function (Blueprint $table) {
            $table->id('db_id');
            $table->uuid('public_id')->unique();
            $table->string('last_name');
            $table->string('email')->unique();
            $table->enum('gender', ['M', 'F']);
            $table->enum('title', ['Mr', 'Ms', 'Mrs', 'Dr', 'Prof']);
            $table->string('first_name');
            $table->string('phone');
            // Business/staff identifier (e.g. "LEC-IT-001") — NOT unique in the real schema.
            $table->string('id');
            $table->foreignId('department_db_id')
                ->constrained('departments', 'db_id')
                ->cascadeOnDelete();
            $table->date('employment_date');
            $table->foreignId('user_db_id')
                ->constrained('users', 'db_id')
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lecturers');
    }
};
