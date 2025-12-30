<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;
    public function up()
    {
        Schema::create('result_publications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // Ensure unique combination so we don't have duplicate rules
            $table->unique(['program_id', 'semester_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('result_publications');
    }
};
