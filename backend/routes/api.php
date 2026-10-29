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
use App\Modules\Lines\Http\Controllers\ShiftPatternGroupController;
use App\Modules\Notifications\Http\Controllers\PushSubscriptionController;
use App\Modules\Reporting\Http\Controllers\ReportController;
use App\Modules\Scheduling\Http\Controllers\AlternativeCandidateController;
use App\Modules\Scheduling\Http\Controllers\EmployeeScheduleController;
use App\Modules\Scheduling\Http\Controllers\ScheduleController;
use App\Modules\Scheduling\Http\Controllers\ScheduleSuggestionController;
use App\Modules\Scheduling\Http\Controllers\SelfServiceScheduleAcknowledgmentController;
use App\Modules\Scheduling\Http\Controllers\ShiftAssignmentController;
use App\Modules\ShiftNotices\Http\Controllers\SelfServiceShiftNoticeController;
use App\Modules\ShiftNotices\Http\Controllers\ShiftNoticeController;
use App\Modules\Staff\Http\Controllers\AgencyController;
use App\Modules\Staff\Http\Controllers\EmployeeController;
use App\Modules\Staff\Http\Controllers\SelfServiceProfileController;
use App\Modules\TimeAttendance\Http\Controllers\TimeClockEntryController;
use App\Modules\TimeAttendance\Http\Controllers\TimeClockImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Public, token-only (ProjectPlan.md §8g) - the invited person hasn't authenticated
    // yet, this request *is* the identity check.
    Route::post('/auth/complete-invitation', [AuthController::class, 'completeInvitation']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Employee self-service surface (ProjectPlan.md §15/§8b) - open to both `admin` and
        // `user` roles, since an admin account may also be a linked Employee. Per-endpoint
        // authorization happens inside each controller (resolving the Employee for the
        // authenticated User, 403 if none linked), not via route middleware.
        Route::prefix('me')->group(function () {
            Route::get('/schedule', [EmployeeScheduleController::class, 'show']);
            Route::get('/profile', [SelfServiceProfileController::class, 'show']);
            Route::get('/company-settings', [CompanySettingsController::class, 'showForEmployee']);
            Route::get('/leave-requests', [SelfServiceLeaveRequestController::class, 'index']);
            Route::post('/leave-requests', [SelfServiceLeaveRequestController::class, 'store']);
            Route::get('/shift-notices/eligibility', [SelfServiceShiftNoticeController::class, 'eligibility']);
            Route::post('/shift-notices', [SelfServiceShiftNoticeController::class, 'store']);

            Route::get('/pending-acknowledgments', [SelfServiceScheduleAcknowledgmentController::class, 'index']);
            Route::post('/schedules/{schedule}/acknowledge', [SelfServiceScheduleAcknowledgmentController::class, 'store']);

            Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store']);
            Route::delete('/push-subscriptions/{pushSubscription}', [PushSubscriptionController::class, 'destroy']);
        });

        Route::middleware('role:admin|super_admin')->group(function () {
            Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
            Route::get('/dashboard/planning-calendar', [DashboardController::class, 'planningCalendar']);

            Route::get('/company-settings', [CompanySettingsController::class, 'show']);
            Route::put('/company-settings', [CompanySettingsController::class, 'update']);

            Route::middleware('role:super_admin')->group(function () {
                Route::put('/company-settings/fairness-weights', [CompanySettingsController::class, 'updateFairnessWeights']);
                Route::put('/company-settings/planner', [CompanySettingsController::class, 'updatePlannerSettings']);
            });

            Route::get('/users', [UserController::class, 'index']);
            Route::put('/users/{user}/visibility-scope', [UserController::class, 'updateVisibilityScope']);
            Route::post('/users/invite', [AuthController::class, 'invite']);
            Route::post('/users/{user}/activate', [AuthController::class, 'activate']);

            Route::apiResource('agencies', AgencyController::class);

            Route::apiResource('employees', EmployeeController::class);
            Route::delete('/employees/{employee}/permanent', [EmployeeController::class, 'destroyPermanently']);
            Route::put('/employees/{employee}/user', [EmployeeController::class, 'linkUser']);
            Route::delete('/employees/{employee}/user', [EmployeeController::class, 'unlinkUser']);
            Route::get('/employees/{employee}/employment-terms', [EmployeeController::class, 'showEmploymentTerm']);
            Route::put('/employees/{employee}/employment-terms', [EmployeeController::class, 'updateEmploymentTerm']);
            Route::put('/employees/{employee}/qualified-roles', [SchedulingRoleController::class, 'syncQualifications']);
            Route::put('/employees/{employee}/unavailability', [EmployeeController::class, 'updateUnavailability']);
            Route::put('/employees/{employee}/role-exclusions', [EmployeeController::class, 'updateRoleExclusions']);
            Route::put('/employees/{employee}/notes', [EmployeeController::class, 'updateNotes']);

            Route::apiResource('lines', LineController::class);
            Route::delete('/lines/{line}/permanent', [LineController::class, 'destroyPermanently']);
            Route::apiResource('roles', SchedulingRoleController::class)->parameters(['roles' => 'scheduling_role']);
            Route::delete('/roles/{scheduling_role}/permanent', [SchedulingRoleController::class, 'destroyPermanently']);
            Route::apiResource('shift-patterns', ShiftPatternController::class)->parameters(['shift-patterns' => 'shift_pattern']);
            Route::apiResource('shift-pattern-groups', ShiftPatternGroupController::class)
                ->parameters(['shift-pattern-groups' => 'shift_pattern_group']);
            Route::apiResource('pay-rate-surcharge-rules', PayRateSurchargeRuleController::class)
                ->parameters(['pay-rate-surcharge-rules' => 'pay_rate_surcharge_rule']);

            Route::get('/leave-types', [LeaveTypeController::class, 'index']);
            Route::post('/leave-types', [LeaveTypeController::class, 'store']);

            Route::apiResource('leave-requests', LeaveRequestController::class)
                ->parameters(['leave-requests' => 'leave_request']);
            Route::post('/leave-requests/{leave_request}/approve', [LeaveRequestController::class, 'approve']);
            Route::post('/leave-requests/{leave_request}/reject', [LeaveRequestController::class, 'reject']);

            Route::apiResource('schedules', ScheduleController::class);
            Route::post('/schedules/suggest', [ScheduleSuggestionController::class, 'store']);
            Route::get('/schedules/suggest/{run}', [ScheduleSuggestionController::class, 'show'])->whereUuid('run');
            Route::post('/schedules/{schedule}/submit', [ScheduleController::class, 'submit']);
            Route::get('/schedules/{schedule}/alternative-candidates', [AlternativeCandidateController::class, 'index']);
            Route::get('/schedules/{schedule}/acknowledgments', [ScheduleController::class, 'acknowledgments']);

            Route::middleware('role:super_admin')->group(function () {
                Route::post('/schedules/{schedule}/approve', [ScheduleController::class, 'approve']);
                Route::post('/schedules/{schedule}/reject', [ScheduleController::class, 'reject']);
                Route::post('/schedules/{schedule}/unlock', [ScheduleController::class, 'unlock']);
            });

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

            Route::get('/reports', [ReportController::class, 'show']);
            Route::get('/reports/export/pdf', [ReportController::class, 'exportPdf']);
            Route::get('/reports/export/excel', [ReportController::class, 'exportExcel']);
        });
    });
});
