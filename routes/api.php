<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Import All Admin Controllers
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\DepartmentController;
use App\Http\Controllers\Api\Admin\ProgramController;
use App\Http\Controllers\Api\Admin\CourseController;
use App\Http\Controllers\Api\Admin\StudentController;
use App\Http\Controllers\Api\Admin\LecturerController;
use App\Http\Controllers\Api\Admin\SemesterController;
use App\Http\Controllers\Api\Admin\TimetableController;
use App\Http\Controllers\Api\Admin\ExamController;
use App\Http\Controllers\Api\Admin\FinanceController;
use App\Http\Controllers\Api\Core\AuthController; // Assuming you have a basic AuthController for login

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public Authentication
Route::post('auth/login', [AuthController::class, 'login']);

// =========================================================================
// ADMIN MODULE
// =========================================================================
Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {

    // --- Dashboard ---
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    // --- Academics: Structure ---
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::post('/departments', [DepartmentController::class, 'store']);

    Route::get('/programs', [ProgramController::class, 'index']);
    Route::post('/programs', [ProgramController::class, 'store']);
    Route::post('/programs/{public_id}/courses', [ProgramController::class, 'addCourse']);

    Route::get('/courses', [CourseController::class, 'index']);
    Route::post('/courses', [CourseController::class, 'store']);

    // --- User Management ---
    Route::get('/students', [StudentController::class, 'index']);
    Route::post('/students', [StudentController::class, 'store']);
    Route::post('/students/batch-upload', [StudentController::class, 'batchUpload']); // If implemented

    Route::get('/lecturers', [LecturerController::class, 'index']);
    Route::post('/lecturers', [LecturerController::class, 'store']);

    // --- Logistics: Time & Levels ---
    Route::get('/semesters', [SemesterController::class, 'index']);
    Route::post('/semesters', [SemesterController::class, 'store']); // Create new Term/Level

    Route::post('/semesters/{public_id}/timetable', [TimetableController::class, 'store']);

    // --- Exams ---
    Route::post('/exams/seasons', [ExamController::class, 'storeSeason']);
    Route::post('/exams/seasons/{public_id}/generate-numbers', [ExamController::class, 'generateNumbers']);
    Route::post('/exams/schedules', [ExamController::class, 'storeSchedule']);

    // --- Finance ---
    Route::post('/finance/fees', [FinanceController::class, 'storeFee']);
    Route::get('/finance/transactions', [FinanceController::class, 'indexTransactions']);
    Route::post('/finance/transactions', [FinanceController::class, 'storeTransaction']);

});

// =========================================================================
// OTHER MODULES (Lecturer/Student Portals) - Future Placeholders
// =========================================================================
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
