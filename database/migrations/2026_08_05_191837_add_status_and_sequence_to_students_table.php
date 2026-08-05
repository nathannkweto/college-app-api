<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'graduated', 'suspended'])
                ->default('active')
                ->after('phone');
            $table->unsignedTinyInteger('current_semester_sequence')->default(1)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['status', 'current_semester_sequence']);
        });
    }
};
