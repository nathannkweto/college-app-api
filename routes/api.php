<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Base URL: https://matem.edu/api/v1/...
|
*/

Route::prefix('v1')->group(function () {

    // =========================================================================
    // AUTHENTICATION (Public)
    // =========================================================================
    Route::prefix('auth')->namespace('App\Http\Controllers\Api\Core')->group(function () {
        Route::post('login', 'AuthController@login')->name('auth.login');
    });


    // =========================================================================
    // PROTECTED ROUTES (Requires Bearer Token)
    // =========================================================================
    Route::middleware('auth:sanctum')->group(function () {

        // --- Auth: Logout ---
        Route::post('auth/logout', [\App\Http\Controllers\Api\Core\AuthController::class, 'logout'])->name('auth.logout');

        // =====================================================================
        // 1. ADMIN MODULE
        // Prefix: /api/v1/admin
        // Namespace: App\Http\Controllers\Api\Admin
        // =====================================================================
        Route::group([
            'prefix' => 'admin',
            'namespace' => 'App\Http\Controllers\Api\Admin',
            'as' => 'admin.'
        ], function () {

            // --- Dashboard ---
            Route::prefix('dashboard')->controller('DashboardController')->group(function () {
                Route::get('metrics', 'metrics');
                Route::get('finance', 'finance');
            });

            // --- Academics: Departments, Qualifications, Courses ---
            Route::apiResource('departments', 'DepartmentController')->only(['index', 'store']);
            Route::apiResource('qualifications', 'QualificationController')->only(['index', 'store']);
            Route::apiResource('courses', 'CourseController')->only(['index', 'store']);

            // --- Academics: Programs & Curriculum ---
            Route::controller('ProgramController')->group(function () {
                Route::get('programs', 'index');
                Route::post('programs', 'store');
                // Program-Course Linking
                Route::get('programs/{public_id}/courses', 'getCourses');
                Route::post('programs/{public_id}/courses', 'attachCourse');
                Route::delete('programs/{public_id}/courses/{course_public_id}', 'detachCourse');
            });

            // --- Students & Promotion ---
            Route::controller('StudentController')->group(function () {
                Route::get('students', 'index');
                Route::post('students', 'store');
                Route::post('students/batch-upload', 'batchUpload');
                Route::post('students/promotion-preview', 'promotionPreview');
                Route::post('students/promote', 'promote');
            });

            // --- Lecturers ---
            Route::controller('LecturerController')->group(function () {
                Route::get('lecturers', 'index');
                Route::post('lecturers', 'store');
                Route::post('lecturers/batch-upload', 'batchUpload');
            });

            // --- Logistics: Semesters ---
            Route::controller('SemesterController')->group(function () {
                Route::get('semesters', 'index');
                Route::post('semesters', 'store');
                Route::get('semesters/active', 'active');
                Route::post('semesters/{public_id}/end', 'end');
            });

            // --- Logistics: Timetables ---
            Route::controller('TimetableController')->prefix('logistics')->group(function () {
                Route::get('timetable', 'index');
                Route::post('timetable', 'store');
            });

            // --- Exams ---
            Route::prefix('exams')->group(function () {
                Route::prefix('seasons')->controller('ExamSeasonController')->group(function () {
                    Route::get('/', 'index');
                    Route::post('/', 'store');
                    Route::get('active', 'active');
                    Route::post('{public_id}/end', 'endSeason');
                });

                Route::prefix('schedules')->controller('ExamScheduleController')->group(function () {
                    Route::get('/', 'index');
                    Route::post('/', 'store');
                });
            });

            // --- Finance ---
            Route::controller('FinanceController')->prefix('finance')->group(function () {
                // Route::get('fees', 'indexFees');
                Route::post('fees', 'storeFee');
                Route::get('students/{student_id}/fees', 'getStudentFees');

                Route::get('transactions', 'indexTransactions');
                Route::post('transactions', 'storeTransaction');
            });

            // --- Results & Publishing ---
            Route::controller('ResultController')->prefix('results')->group(function () {
                Route::get('program-summary', 'programSummary');
                Route::get('student-transcript', 'studentTranscript');
                Route::post('publish', 'publish');
            });
        });


        // =====================================================================
        // 2. LECTURER MODULE
        // Prefix: /api/v1/lecturer
        // Namespace: App\Http\Controllers\Api\Lecturer
        // =====================================================================
        Route::group([
            'prefix' => 'lecturer',
            'namespace' => 'App\Http\Controllers\Api\Lecturer',
            'as' => 'lecturer.'
        ], function () {

            // --- Profile & Dashboard ---
            Route::get('profile', 'ProfileController@show');
            Route::get('schedule', 'ScheduleController@index');

            // --- Courses ---
            Route::controller('CourseController')->prefix('courses')->group(function () {
                Route::get('/', 'index');
                Route::get('/{publicId}', 'show');
            });
            Route::post('courses/{course_public_id}/grades', 'GradeController@store');
        });


        // =====================================================================
        // 3. STUDENT MODULE
        // Prefix: /api/v1/student
        // Namespace: App\Http\Controllers\Api\Student
        // =====================================================================
        Route::group([
            'prefix' => 'student',
            'namespace' => 'App\Http\Controllers\Api\Student',
            'as' => 'student.'
        ], function () {

            // --- Profile & Dashboard ---
            Route::get('profile', 'ProfileController@show');
            Route::get('schedule', 'ScheduleController@index');

            // --- Academics ---
            Route::get('courses/current', 'CourseController@current');
            Route::get('curriculum', 'CurriculumController@index');
            Route::get('results', 'ResultController@index');
            Route::get('exams/upcoming', 'ExamController@upcoming');

            // --- Finance ---
            Route::get('finance', 'FinanceController@index');
        });

    }); // End Middleware Auth

}); // End Prefix v1
