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
        Schema::create('fees', function (Blueprint $table) {
            $table->id('db_id');
            $table->uuid('public_id')->unique();
            $table->foreignId('student_db_id')
                ->constrained('students', 'db_id')
                ->cascadeOnDelete();
            $table->enum('status', ['pending', 'paid']);
            $table->foreignId('transaction_db_id')
                ->constrained('transactions', 'db_id')
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fees');
    }
};
