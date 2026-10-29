<?php

namespace App\Modules\Staff\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Staff\Support\ResolvesAuthenticatedEmployee;
use Illuminate\Http\Request;

/**
 * The employee dashboard's "who am I" card (ProjectPlan.md §8f) - a few read-only facts an
 * employee would otherwise have no self-service way to see: their contracted hours, default
 * line, and whether they're marked as a shift lead. Deliberately minimal - this is not a
 * profile-editing surface, just a glance at what's on file.
 */
class SelfServiceProfileController extends Controller
{
    use ResolvesAuthenticatedEmployee;

    public function show(Request $request)
    {
        $employee = $this->employeeForUser($request->user())
            ->load(['defaultLine', 'currentEmploymentTerm']);

        return response()->json([
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'email' => $request->user()->email,
            'is_shift_lead' => $employee->is_shift_lead,
            'default_line' => $employee->defaultLine ? [
                'id' => $employee->defaultLine->id,
                'name' => $employee->defaultLine->name,
            ] : null,
            'employment_term' => $employee->currentEmploymentTerm ? [
                'min_weekly_hours' => $employee->currentEmploymentTerm->min_weekly_hours,
                'max_weekly_hours' => $employee->currentEmploymentTerm->max_weekly_hours,
                'effective_from' => $employee->currentEmploymentTerm->effective_from?->toDateString(),
            ] : null,
        ]);
    }
}
