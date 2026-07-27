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
        Schema::create('semesters', function (Blueprint $table) {
            $table->id('db_id');
            $table->uuid('public_id')->nullable()->unique();
            // Legacy string column kept alongside the FK below — both exist in the real schema.
            $table->string('academic_year')->nullable();
            $table->foreignId('academic_year_db_id')
                ->nullable()
                ->constrained('academic_years', 'db_id')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('semester_number');
            $table->boolean('is_active')->default(false);
            $table->date('start_date')->nullable();
            $table->unsignedInteger('length_weeks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('semesters');
    }
};
