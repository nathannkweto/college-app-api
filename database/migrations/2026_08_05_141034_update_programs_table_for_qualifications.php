<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            // YAML uses "code" instead of "tag"
            if (!Schema::hasColumn('programs', 'code')) {
                $table->string('code', 20)->nullable()->after('name');
            }

            if (!Schema::hasColumn('programs', 'total_semesters')) {
                $table->unsignedTinyInteger('total_semesters')->default(6)->after('code');
            }

            if (!Schema::hasColumn('programs', 'qualification_db_id')) {
                $table->foreignId('qualification_db_id')
                    ->nullable()
                    ->after('department_db_id')
                    ->constrained('qualifications', 'db_id')
                    ->nullOnDelete();
            }
        });

        // Copy existing tag → code where code is empty
        \DB::table('programs')->whereNull('code')->update([
            'code' => \DB::raw('tag'),
        ]);
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qualification_db_id');
            $table->dropColumn(['code', 'total_semesters']);
        });
    }
};
