<?php

use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\Auth\Http\Controllers\UserController;
use App\Modules\Staff\Http\Controllers\AgencyController;
use App\Modules\Staff\Http\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::middleware('role:admin')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::put('/users/{user}/visibility-scope', [UserController::class, 'updateVisibilityScope']);

            Route::apiResource('agencies', AgencyController::class);

            Route::apiResource('employees', EmployeeController::class);
            Route::put('/employees/{employee}/user', [EmployeeController::class, 'linkUser']);
            Route::delete('/employees/{employee}/user', [EmployeeController::class, 'unlinkUser']);
            Route::get('/employees/{employee}/employment-terms', [EmployeeController::class, 'showEmploymentTerm']);
            Route::put('/employees/{employee}/employment-terms', [EmployeeController::class, 'updateEmploymentTerm']);
        });
    });
});
