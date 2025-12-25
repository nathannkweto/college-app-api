<?php declare(strict_types=1);

use App\Http\Controllers\Api\Core\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'role:ADMIN'])->prefix('admin')->group(function () {

    // This is the route we are about to build next
    Route::post('/users', function() {
        return response()->json(['message' => 'Hello Admin']);
    });

});
