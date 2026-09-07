<?php

namespace Database\Factories;

use App\Modules\Lines\Models\Line;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\Staff\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftAssignment>
 */
class ShiftAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'schedule_id' => Schedule::factory(),
            'employee_id' => Employee::factory(),
            'line_id' => Line::factory(),
            'shift_pattern_id' => null,
            'work_date' => fake()->dateTimeBetween('-1 year', '+1 year')->format('Y-m-d'),
            'role_id' => null,
            'starts_at' => null,
            'ends_at' => null,
            'status' => ShiftAssignment::STATUS_PROPOSED,
            'source' => ShiftAssignment::SOURCE_AUTO_SUGGESTED,
            'notes' => null,
        ];
    }

    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => ShiftAssignment::SOURCE_MANUAL,
        ]);
    }
}
