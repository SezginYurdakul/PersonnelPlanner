<?php

namespace App\Modules\Reporting\DTOs;

/**
 * One row in the cost breakdown (ProjectPlan.md §14.2) - either the `vast` subtotal, or
 * one `uitzendkracht` agency's subtotal. `agencyName` is null for the `vast` row.
 */
final readonly class CostBreakdownRowData
{
    public function __construct(
        public string $employeeType,
        public ?string $agencyName,
        public float $totalHours,
        public float $totalCost,
    ) {}
}
