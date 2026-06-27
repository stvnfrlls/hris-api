<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\LeaveTypeController;
use App\Http\Controllers\Api\PositionController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('employees', EmployeeController::class);

    Route::prefix('attendance')->group(function () {
        Route::get('/', [AttendanceController::class, 'index']);
        Route::post('clock-in', [AttendanceController::class, 'clockIn']);
        Route::post('clock-out', [AttendanceController::class, 'clockOut']);
        Route::get('{attendance}', [AttendanceController::class, 'show']);
        Route::put('{attendance}', [AttendanceController::class, 'update']);
    });

    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('positions', PositionController::class);

    Route::apiResource('leave-types', LeaveTypeController::class)
        ->except(['show']);

    Route::prefix('leave-requests')->group(function () {
        Route::get('/',[LeaveRequestController::class, 'index']);
        Route::post('/',[LeaveRequestController::class, 'store']);
        Route::get('/balance',[LeaveRequestController::class, 'balance']);
        Route::get('/{leaveRequest}',[LeaveRequestController::class, 'show']);
        Route::post('/{leaveRequest}/approve',[LeaveRequestController::class, 'approve']);
        Route::post('/{leaveRequest}/reject',[LeaveRequestController::class, 'reject']);
        Route::post('/{leaveRequest}/cancel',[LeaveRequestController::class, 'cancel']);
    });
});
