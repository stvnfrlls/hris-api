<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\LeaveTypeController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PayrollPeriodController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SalaryDetailController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
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
        Route::get('/', [LeaveRequestController::class, 'index']);
        Route::post('/', [LeaveRequestController::class, 'store']);
        Route::get('/balance', [LeaveRequestController::class, 'balance']);
        Route::get('/{leaveRequest}', [LeaveRequestController::class, 'show']);
        Route::post('/{leaveRequest}/approve', [LeaveRequestController::class, 'approve']);
        Route::post('/{leaveRequest}/reject', [LeaveRequestController::class, 'reject']);
        Route::post('/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel']);
    });

    Route::get('salary', [SalaryDetailController::class, 'index']);
    Route::post('salary', [SalaryDetailController::class, 'store']);
    Route::get('salary/{employee}', [SalaryDetailController::class, 'show']);
    Route::put('salary/{employee}', [SalaryDetailController::class, 'update']);

    Route::get('payroll-periods', [PayrollPeriodController::class, 'index']);
    Route::post('payroll-periods', [PayrollPeriodController::class, 'store']);
    Route::get('payroll-periods/{payrollPeriod}', [PayrollPeriodController::class, 'show']);

    Route::get('payroll', [PayrollController::class, 'index']);
    Route::post('payroll/generate', [PayrollController::class, 'generate']);
    Route::get('payroll/{payroll}', [PayrollController::class, 'show']);
    Route::post('payroll/{payroll}/release', [PayrollController::class, 'release']);

    Route::prefix('reports')->group(function () {
        Route::get('attendance-summary', [ReportController::class, 'attendanceSummary']);
        Route::get('tardiness', [ReportController::class, 'tardiness']);
        Route::get('payroll-summary', [ReportController::class, 'payrollSummary']);
        Route::get('headcount', [ReportController::class, 'headcount']);
    });
});
