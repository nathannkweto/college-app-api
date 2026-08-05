<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            if (!Schema::hasColumn('lecturers', 'national_id_number')) {
                $table->string('national_id_number')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('lecturers', 'dob')) {
                $table->date('dob')->nullable()->after('national_id_number');
            }
            if (!Schema::hasColumn('lecturers', 'address')) {
                $table->string('address')->nullable()->after('dob');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            $table->dropColumn(['national_id_number', 'dob', 'address']);
        });
    }
};
