<?php declare(strict_types=1);

use App\Http\Controllers\Api\Core\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

