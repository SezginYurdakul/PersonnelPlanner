<?php

namespace App\Modules\Scheduling\Contracts;

use App\Modules\Scheduling\DTOs\MoveAssignmentData;
use App\Modules\Scheduling\DTOs\ShiftAssignmentData;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\Scheduling\RuleEngine\ValueObjects\RuleResult;
use Illuminate\Support\Collection;

interface ShiftAssignmentServiceContract
{
    /**
     * @return array{assignment: ShiftAssignment, rule_results: Collection<int, RuleResult>}
     */
    public function create(Schedule $schedule, ShiftAssignmentData $data): array;

    /**
     * @return array{assignment: ShiftAssignment, rule_results: Collection<int, RuleResult>}
     */
    public function update(ShiftAssignment $assignment, ShiftAssignmentData $data): array;

    /**
     * @return array{assignment: ShiftAssignment, rule_results: Collection<int, RuleResult>}
     */
    public function move(ShiftAssignment $assignment, MoveAssignmentData $data): array;

    public function delete(ShiftAssignment $assignment): void;
}
