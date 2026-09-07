<?php

namespace App\Modules\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Contracts\ShiftAssignmentServiceContract;
use App\Modules\Scheduling\Http\Requests\MoveShiftAssignmentRequest;
use App\Modules\Scheduling\Http\Requests\StoreShiftAssignmentRequest;
use App\Modules\Scheduling\Http\Requests\UpdateShiftAssignmentRequest;
use App\Modules\Scheduling\Http\Resources\ShiftAssignmentResource;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ShiftAssignment;
use Illuminate\Http\Response;

class ShiftAssignmentController extends Controller
{
    public function __construct(private readonly ShiftAssignmentServiceContract $assignments)
    {
    }

    public function store(StoreShiftAssignmentRequest $request)
    {
        $schedule = Schedule::query()->findOrFail($request->input('schedule_id'));

        $result = $this->assignments->create($schedule, $request->toDto());

        return $this->mutationResponse($result);
    }

    public function update(UpdateShiftAssignmentRequest $request, ShiftAssignment $shiftAssignment)
    {
        $result = $this->assignments->update($shiftAssignment, $request->toDto());

        return $this->mutationResponse($result);
    }

    public function move(MoveShiftAssignmentRequest $request, ShiftAssignment $shiftAssignment)
    {
        $result = $this->assignments->move($shiftAssignment, $request->toDto());

        return $this->mutationResponse($result);
    }

    public function destroy(ShiftAssignment $shiftAssignment): Response
    {
        $this->assignments->delete($shiftAssignment);

        return response()->noContent();
    }

    /**
     * @param  array{assignment: ShiftAssignment, rule_results: \Illuminate\Support\Collection}  $result
     */
    private function mutationResponse(array $result)
    {
        $result['assignment']->load(['employee', 'line', 'shiftPattern', 'role']);

        return response()->json([
            'assignment' => new ShiftAssignmentResource($result['assignment']),
            'rule_results' => $result['rule_results']->map(fn ($r) => [
                'rule_key' => $r->ruleKey,
                'status' => $r->status,
                'message' => $r->message,
            ]),
        ]);
    }
}
