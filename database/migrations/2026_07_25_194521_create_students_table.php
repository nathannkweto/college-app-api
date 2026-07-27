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
        Schema::create('students', function (Blueprint $table) {
            $table->id('db_id');
            $table->uuid('public_id')->unique();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('national_id_number');
            $table->enum('gender', ['M', 'F'])->nullable();
            $table->string('email')->nullable();
            $table->date('dob')->nullable();
            $table->string('address')->nullable();
            // Business/student identifier (e.g. "2025-BA-001") — NOT unique in the real schema.
            $table->string('id');
            $table->string('phone');
            $table->foreignId('level_db_id')
                ->nullable()
                ->constrained('levels', 'db_id')
                ->cascadeOnDelete();
            $table->foreignId('program_db_id')
                ->nullable()
                ->constrained('programs', 'db_id')
                ->cascadeOnDelete();
            $table->date('enrollment_date');
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
        Schema::dropIfExists('students');
    }
};
