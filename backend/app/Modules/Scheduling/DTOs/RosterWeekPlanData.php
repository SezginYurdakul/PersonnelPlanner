<?php

namespace App\Modules\Scheduling\DTOs;

use Illuminate\Support\Collection;

/**
 * The roster planner's answer for the week being generated (ProjectPlan.md §28): which
 * lead works which shift, which operator holds which station, and the shift type each person
 * is locked to that week. Only the horizon's first week is kept - the later weeks were
 * lookahead.
 */
final readonly class RosterWeekPlanData
{
    /**
     * @param  Collection<string, Collection<int, int>>  $leadsByShift  "Y-m-d|slotType" => employee ids, in planner order
     * @param  Collection<string, Collection<int, int>>  $operatorsByStation  "Y-m-d|slotType|lineId|roleId" => employee ids
     * @param  Collection<int, string>  $locks  employee_id => slot_type, for everyone working this week
     * @param  Collection<int, int>  $plannedEmployeeIds  everyone the planner planned, working this week or not
     * @param  array<string, mixed>  $summary  the planner's summary of the whole horizon
     */
    public function __construct(
        public Collection $leadsByShift,
        public Collection $operatorsByStation,
        public Collection $locks,
        public Collection $plannedEmployeeIds,
        public array $summary,
    ) {}

    /**
     * Nobody to plan (no shift leads and no qualified operators in scope): every lead
     * shift and station is left open.
     */
    public static function empty(): self
    {
        return new self(collect(), collect(), collect(), collect(), []);
    }

    /**
     * @param  array<string, mixed>  $response  the planner's JSON answer
     */
    public static function fromPlannerResponse(array $response): self
    {
        $leadsByShift = collect();
        $operatorsByStation = collect();
        $locks = collect();

        foreach ($response['weeks'][0]['employees'] ?? [] as $row) {
            $employeeId = (int) $row['employee_id'];

            foreach ($row['shifts'] as $shift) {
                [$kind, $rest] = explode('|', (string) $shift['shift_id'], 2);
                $bucket = $kind === 'lead' ? $leadsByShift : $operatorsByStation;
                $bucket->put($rest, $bucket->get($rest, collect())->push($employeeId));
            }

            // Someone the planner had to mix types for is locked to the type they work most.
            $locks->put($employeeId, collect($row['shifts'])->countBy('type')->sortDesc()->keys()->first());
        }

        return new self(
            $leadsByShift,
            $operatorsByStation,
            $locks,
            collect($response['totals'] ?? [])->pluck('employee_id')->map(fn ($id) => (int) $id),
            $response['summary'] ?? [],
        );
    }

    /**
     * @return Collection<int, int>
     */
    public function leadsFor(string $date, string $slotType): Collection
    {
        return $this->leadsByShift->get("{$date}|{$slotType}", collect());
    }

    /**
     * @return Collection<int, int>
     */
    public function operatorsFor(string $date, string $slotType, int $lineId, int $roleId): Collection
    {
        return $this->operatorsByStation->get("{$date}|{$slotType}|{$lineId}|{$roleId}", collect());
    }
}
