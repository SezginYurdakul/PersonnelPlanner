<?php

namespace App\Modules\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Contracts\AlternativeCandidateServiceContract;
use App\Modules\Scheduling\Http\Resources\CandidateResource;
use App\Modules\Scheduling\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AlternativeCandidateController extends Controller
{
    public function __construct(private readonly AlternativeCandidateServiceContract $alternatives)
    {
    }

    public function index(Request $request, Schedule $schedule)
    {
        $validated = $request->validate([
            'line_id' => ['required', 'integer', 'exists:lines,id'],
            'shift_pattern_id' => ['nullable', 'integer', 'exists:shift_patterns,id'],
            'work_date' => ['required', 'date'],
            'role_id' => ['required', 'integer', 'exists:scheduling_roles,id'],
            'ranking_mode' => ['required', 'in:fair,cost'],
        ]);

        $candidates = $this->alternatives->forSlot(
            schedule: $schedule,
            lineId: (int) $validated['line_id'],
            shiftPatternId: $validated['shift_pattern_id'] ?? null,
            workDate: Carbon::parse($validated['work_date']),
            roleId: (int) $validated['role_id'],
            rankingMode: $validated['ranking_mode'],
        );

        return CandidateResource::collection($candidates);
    }
}
