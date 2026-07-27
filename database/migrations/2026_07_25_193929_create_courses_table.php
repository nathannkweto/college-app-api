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
        Schema::create('courses', function (Blueprint $table) {
            $table->id('db_id');
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->foreignId('department_db_id')
                ->constrained('departments', 'db_id')
                ->cascadeOnDelete();
            // No unique constraint in the real schema — existing data has duplicate 0 values.
            $table->integer('code');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
