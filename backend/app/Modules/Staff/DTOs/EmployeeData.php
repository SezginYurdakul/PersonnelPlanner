<?php

namespace App\Modules\Staff\DTOs;

final readonly class EmployeeData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $employeeType,
        public string $payType,
        public ?string $phone = null,
        public ?string $email = null,
        public ?int $agencyId = null,
        public ?float $hourlyRate = null,
        public ?float $monthlySalary = null,
        public ?float $contractedHoursPerWeek = null,
        public ?int $defaultLineId = null,
        public bool $isActive = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'phone' => $this->phone,
            'email' => $this->email,
            'employee_type' => $this->employeeType,
            'agency_id' => $this->agencyId,
            'pay_type' => $this->payType,
            'hourly_rate' => $this->hourlyRate,
            'monthly_salary' => $this->monthlySalary,
            'contracted_hours_per_week' => $this->contractedHoursPerWeek,
            'default_line_id' => $this->defaultLineId,
            'is_active' => $this->isActive,
        ];
    }
}
