<?php

namespace App\Modules\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CompanySettings\Models\CompanySetting;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Scheduling\Http\Resources\EmployeeScheduleDayResource;
use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\ShiftNotices\Models\ShiftNotice;
use App\Modules\Staff\Support\ResolvesAuthenticatedEmployee;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Employee-facing, read-only schedule view (ProjectPlan.md §12.3a/§15/§8f) - distinct
 * from the admin's ScheduleController, which returns one dense Schedule with every
 * assignment. This endpoint resolves the authenticated employee, applies their
 * visibility_scope server-side, and folds in that week's approved leave / shift-notice
 * state so vacation/sick days render correctly.
 */
class EmployeeScheduleController extends Controller
{
    use ResolvesAuthenticatedEmployee;

    public function show(Request $request)
    {
        $employee = $this->employeeForUser($request->user());

        $weekStart = $request->has('week_start_date')
            ? Carbon::parse($request->string('week_start_date')->toString())->startOfDay()
            : CompanySetting::current()->startOfWeekFor(now());
        // The employee's month-calendar view needs a whole grid's worth of weeks (4-6,
        // depending on where the 1st/last of the month fall) in one call rather than one
        // request per visible week - capped at 6 since that's the most any single month
        // grid ever needs.
        $weeks = max(1, min(6, $request->integer('weeks', 1)));
        $weekEnd = $weekStart->copy()->addDays($weeks * 7 - 1)->endOfDay();

        $ownAssignments = ShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$weekStart, $weekEnd])
            ->get();

        $visibilityScope = $request->user()->visibility_scope;

        $assignments = match ($visibilityScope) {
            'company' => ShiftAssignment::query()
                ->whereBetween('work_date', [$weekStart, $weekEnd])
                ->with(['employee', 'line', 'role', 'shiftPattern'])
                ->get(),
            'line' => ShiftAssignment::query()
                ->whereBetween('work_date', [$weekStart, $weekEnd])
                ->whereIn('line_id', $ownAssignments->pluck('line_id')->filter()->unique())
                ->with(['employee', 'line', 'role', 'shiftPattern'])
                ->get(),
            default => $ownAssignments->loadMissing(['employee', 'line', 'role', 'shiftPattern']),
        };

        $leaveRequests = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $weekEnd)
            ->where('end_date', '>=', $weekStart)
            ->get(['start_date', 'end_date']);

        $shiftNotices = ShiftNotice::query()
            ->where('employee_id', $employee->id)
            ->whereHas('shiftAssignment', function ($query) use ($weekStart, $weekEnd) {
                $query->whereBetween('work_date', [$weekStart, $weekEnd]);
            })
            ->with('shiftAssignment:id,work_date')
            ->get(['id', 'shift_assignment_id', 'type', 'delay_minutes', 'status']);

        return response()->json([
            'week_start_date' => $weekStart->toDateString(),
            'range_end_date' => $weekEnd->toDateString(),
            'visibility_scope' => $visibilityScope,
            'assignments' => EmployeeScheduleDayResource::collection($assignments),
            'leave_days' => $leaveRequests->map(fn (LeaveRequest $leaveRequest) => [
                'start_date' => $leaveRequest->start_date?->toDateString(),
                'end_date' => $leaveRequest->end_date?->toDateString(),
            ]),
            'shift_notices' => $shiftNotices->map(fn (ShiftNotice $notice) => [
                'work_date' => $notice->shiftAssignment?->work_date?->toDateString(),
                'type' => $notice->type,
                'delay_minutes' => $notice->delay_minutes,
                'status' => $notice->status,
            ]),
        ]);
    }
}
