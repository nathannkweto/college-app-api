<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;
    public function up()
    {
        // 2. Timetable Entries
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();

            // Context
            $table->foreignId('semester_id')->constrained();
            $table->foreignId('program_course_id')->constrained();
            $table->foreignId('program_id')->nullable()->constrained()->onDelete('cascade');

            // Details
            $table->enum('day', ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN']);
            $table->time('start_time');
            $table->time('end_time');
            $table->string('location')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('timetable_entries');
    }
};
