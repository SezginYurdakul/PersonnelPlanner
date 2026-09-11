<?php

use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\Auth\Http\Controllers\UserController;
use App\Modules\CompanySettings\Http\Controllers\CompanySettingsController;
use App\Modules\Dashboard\Http\Controllers\DashboardController;
use App\Modules\Leave\Http\Controllers\LeaveRequestController;
use App\Modules\Leave\Http\Controllers\LeaveTypeController;
use App\Modules\Leave\Http\Controllers\SelfServiceLeaveRequestController;
use App\Modules\Lines\Http\Controllers\LineController;
use App\Modules\Lines\Http\Controllers\PayRateSurchargeRuleController;
use App\Modules\Lines\Http\Controllers\SchedulingRoleController;
use App\Modules\Lines\Http\Controllers\ShiftPatternController;
use App\Modules\Scheduling\Http\Controllers\AlternativeCandidateController;
use App\Modules\Scheduling\Http\Controllers\EmployeeScheduleController;
use App\Modules\Scheduling\Http\Controllers\ScheduleController;
use App\Modules\Scheduling\Http\Controllers\ScheduleSuggestionController;
use App\Modules\Scheduling\Http\Controllers\ShiftAssignmentController;
use App\Modules\ShiftNotices\Http\Controllers\SelfServiceShiftNoticeController;
use App\Modules\ShiftNotices\Http\Controllers\ShiftNoticeController;
use App\Modules\Staff\Http\Controllers\AgencyController;
use App\Modules\Staff\Http\Controllers\EmployeeController;
use App\Modules\TimeAttendance\Http\Controllers\TimeClockEntryController;
use App\Modules\TimeAttendance\Http\Controllers\TimeClockImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Employee self-service surface (ProjectPlan.md §15/§8b) - open to both `admin` and
        // `user` roles, since an admin account may also be a linked Employee. Per-endpoint
        // authorization happens inside each controller (resolving the Employee for the
        // authenticated User, 403 if none linked), not via route middleware.
        Route::prefix('me')->group(function () {
            Route::get('/schedule', [EmployeeScheduleController::class, 'show']);
            Route::get('/company-settings', [CompanySettingsController::class, 'showForEmployee']);
            Route::get('/leave-requests', [SelfServiceLeaveRequestController::class, 'index']);
            Route::post('/leave-requests', [SelfServiceLeaveRequestController::class, 'store']);
            Route::get('/shift-notices/eligibility', [SelfServiceShiftNoticeController::class, 'eligibility']);
            Route::post('/shift-notices', [SelfServiceShiftNoticeController::class, 'store']);
        });

        Route::middleware('role:admin')->group(function () {
            Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

            Route::get('/company-settings', [CompanySettingsController::class, 'show']);
            Route::put('/company-settings', [CompanySettingsController::class, 'update']);

            Route::get('/users', [UserController::class, 'index']);
            Route::put('/users/{user}/visibility-scope', [UserController::class, 'updateVisibilityScope']);

            Route::apiResource('agencies', AgencyController::class);

            Route::apiResource('employees', EmployeeController::class);
            Route::put('/employees/{employee}/user', [EmployeeController::class, 'linkUser']);
            Route::delete('/employees/{employee}/user', [EmployeeController::class, 'unlinkUser']);
            Route::get('/employees/{employee}/employment-terms', [EmployeeController::class, 'showEmploymentTerm']);
            Route::put('/employees/{employee}/employment-terms', [EmployeeController::class, 'updateEmploymentTerm']);
            Route::put('/employees/{employee}/qualified-roles', [SchedulingRoleController::class, 'syncQualifications']);

            Route::apiResource('lines', LineController::class);
            Route::apiResource('roles', SchedulingRoleController::class)->parameters(['roles' => 'scheduling_role']);
            Route::apiResource('shift-patterns', ShiftPatternController::class)->parameters(['shift-patterns' => 'shift_pattern']);
            Route::apiResource('pay-rate-surcharge-rules', PayRateSurchargeRuleController::class)
                ->parameters(['pay-rate-surcharge-rules' => 'pay_rate_surcharge_rule']);

            Route::get('/leave-types', [LeaveTypeController::class, 'index']);
            Route::post('/leave-types', [LeaveTypeController::class, 'store']);

            Route::apiResource('leave-requests', LeaveRequestController::class)
                ->parameters(['leave-requests' => 'leave_request']);
            Route::post('/leave-requests/{leave_request}/approve', [LeaveRequestController::class, 'approve']);
            Route::post('/leave-requests/{leave_request}/reject', [LeaveRequestController::class, 'reject']);

            Route::apiResource('schedules', ScheduleController::class)->except(['update']);
            Route::post('/schedules/suggest', [ScheduleSuggestionController::class, 'store']);
            Route::post('/schedules/{schedule}/approve', [ScheduleController::class, 'approve']);
            Route::get('/schedules/{schedule}/alternative-candidates', [AlternativeCandidateController::class, 'index']);

            Route::apiResource('shift-assignments', ShiftAssignmentController::class)
                ->parameters(['shift-assignments' => 'shift_assignment'])
                ->only(['store', 'update', 'destroy']);
            Route::patch('/shift-assignments/{shift_assignment}/move', [ShiftAssignmentController::class, 'move']);

            Route::post('/time-clock-imports/preview', [TimeClockImportController::class, 'preview']);
            Route::post('/time-clock-imports/commit', [TimeClockImportController::class, 'commit']);
            Route::apiResource('time-clock-entries', TimeClockEntryController::class)
                ->parameters(['time-clock-entries' => 'time_clock_entry']);

            Route::get('/shift-notices', [ShiftNoticeController::class, 'index']);
            Route::post('/shift-notices/{shift_notice}/acknowledge', [ShiftNoticeController::class, 'acknowledge']);
        });
    });
});
