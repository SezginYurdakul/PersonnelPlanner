<?php

namespace Database\Seeders;

use App\Modules\Lines\Models\Line;
use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Staff\Models\Agency;
use App\Modules\Staff\Models\Employee;
use App\Modules\Staff\Models\EmploymentTerm;
use Illuminate\Database\Seeder;

/**
 * Test data reconstructed from the real customer's paper rooster (Excel screenshots, weeks
 * 31-36, July-September 2026) - real employee names, real line assignments, real station
 * labels observed on Lijn 3. Built for testing the Weekly Scheduling module (Phase 5)
 * against a realistic-scale, realistic-shape workforce.
 *
 * NOT idempotent by design (this is throwaway test data, not production seed data) - run
 * once against a clean slate.
 */
class BakeryXTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $lines = $this->createLines();
        $agencies = $this->createAgencies();
        $roles = $this->createRoles($lines);

        $this->createLeidingEmployees($roles);
        $this->createLijn1Employees($lines['lijn1'], $roles);
        $this->createLijn2Employees($lines['lijn2']);
        $this->createLijn3Employees($lines['lijn3'], $roles, $agencies['flex'], $agencies['vzm']);
    }

    /**
     * @return array<string, Line>
     */
    private function createLines(): array
    {
        return [
            'lijn1' => $this->renameOrCreateLine('L1', 'LIJN1', 'Lijn 1'),
            'lijn2' => $this->renameOrCreateLine('L2', 'LIJN2', 'Lijn 2'),
            'lijn3' => $this->renameOrCreateLine('L3', 'LIJN3', 'Lijn 3'),
        ];
    }

    /**
     * Renames an existing Line (matched by its old code, e.g. from earlier manual testing)
     * to the real naming, preserving its id/FKs; creates it fresh if no such row exists.
     */
    private function renameOrCreateLine(string $oldCode, string $newCode, string $newName): Line
    {
        $existing = Line::where('code', $oldCode)->first();

        if ($existing !== null) {
            $existing->update(['name' => $newName, 'code' => $newCode, 'is_active' => true]);

            return $existing;
        }

        return Line::updateOrCreate(['code' => $newCode], ['name' => $newName, 'is_active' => true]);
    }

    /**
     * @return array<string, Agency>
     */
    private function createAgencies(): array
    {
        return [
            'flex' => Agency::updateOrCreate(['code' => 'FLEX'], ['name' => 'Flex', 'is_active' => true]),
            'vzm' => Agency::updateOrCreate(['code' => 'VZM'], ['name' => 'VZM', 'is_active' => true]),
        ];
    }

    /**
     * @param  array<string, Line>  $lines
     * @return array<string, SchedulingRole>
     */
    private function createRoles(array $lines): array
    {
        $roles = [];

        // Lijn 3 station labels observed directly in the rooster cells (draai/snij/doop/deeg).
        $lijn3StationNames = [
            'draai' => 'Draai (Lijn 3)',
            'snij' => 'Snij (Lijn 3)',
            'doop' => 'Doop (Lijn 3)',
            'deeg' => 'Deeg (Lijn 3)',
        ];

        foreach ($lijn3StationNames as $key => $name) {
            $roles["lijn3_{$key}"] = SchedulingRole::updateOrCreate(
                ['name' => $name, 'line_id' => $lines['lijn3']->id],
                ['role_kind' => SchedulingRole::KIND_STATION, 'requires_coverage' => true, 'is_active' => true],
            );
        }

        // A generic Lijn 1 / Lijn 2 station, since the rooster doesn't break those lines
        // into named sub-stations the way Lijn 3 is - just start-time entries per person.
        $roles['lijn1_operator'] = SchedulingRole::updateOrCreate(
            ['name' => 'Lijn 1 Operator', 'line_id' => $lines['lijn1']->id],
            ['role_kind' => SchedulingRole::KIND_STATION, 'requires_coverage' => true, 'is_active' => true],
        );
        $roles['lijn2_operator'] = SchedulingRole::updateOrCreate(
            ['name' => 'Lijn 2 Operator', 'line_id' => $lines['lijn2']->id],
            ['role_kind' => SchedulingRole::KIND_STATION, 'requires_coverage' => true, 'is_active' => true],
        );

        // Leiding (leadership) secondary tasks - line-independent, never mandatory coverage.
        $roles['silo_watch'] = SchedulingRole::updateOrCreate(
            ['name' => 'Silo Watch', 'line_id' => null],
            ['role_kind' => SchedulingRole::KIND_SECONDARY_TASK, 'attachment_type' => 'none', 'requires_coverage' => false, 'is_active' => true],
        );
        $roles['3pl_desem'] = SchedulingRole::updateOrCreate(
            ['name' => '3PL Desem', 'line_id' => null],
            ['role_kind' => SchedulingRole::KIND_SECONDARY_TASK, 'attachment_type' => 'none', 'requires_coverage' => false, 'is_active' => true],
        );

        return $roles;
    }

    /**
     * @param  array<string, SchedulingRole>  $roles
     */
    private function createLeidingEmployees(array $roles): void
    {
        $names = ['Menno', 'Sander', 'Mark', 'Marc', 'Erdal', 'Winfred', 'Kars'];

        foreach ($names as $name) {
            $employee = $this->makeEmployee($name, 'Leiding', null);
            $employee->schedulingRoles()->syncWithoutDetaching([$roles['silo_watch']->id, $roles['3pl_desem']->id]);
        }
    }

    /**
     * @param  array<string, SchedulingRole>  $roles
     */
    private function createLijn1Employees(Line $lijn1, array $roles): void
    {
        $names = ['Roland', 'David', 'Jaco', 'Hendrick', 'Sezgin', 'Kevin', 'Erik'];

        foreach ($names as $name) {
            $employee = $this->makeEmployee($name, 'Lijn1', $lijn1->id);
            $employee->schedulingRoles()->syncWithoutDetaching([$roles['lijn1_operator']->id]);
        }
    }

    private function createLijn2Employees(Line $lijn2): void
    {
        $names = ['Ali', 'Ruud', 'Berkhli', 'Serkan', 'Carlos', 'Flavio'];
        $lijn2Operator = SchedulingRole::where('name', 'Lijn 2 Operator')->firstOrFail();

        foreach ($names as $name) {
            $employee = $this->makeEmployee($name, 'Lijn2', $lijn2->id);
            $employee->schedulingRoles()->syncWithoutDetaching([$lijn2Operator->id]);
        }
    }

    /**
     * @param  array<string, SchedulingRole>  $roles
     */
    private function createLijn3Employees(Line $lijn3, array $roles, Agency $flex, Agency $vzm): void
    {
        // Named permanent staff observed on Lijn 3, each qualified for the station(s) their
        // rooster row's task label indicated across the six weeks of screenshots.
        $namedStaffStations = [
            'Frank' => ['draai'],
            'Nicusor' => ['draai'],
            'Mustafa' => ['snij', 'doop'],
            'Luca' => [],
            'Jan' => ['snij'],
            'Sem' => [],
            'Justin' => [],
            'Tanja' => ['doop'],
            'Mahamud' => ['doop', 'snij'],
            'Ayshe' => ['snij'],
            'Arda' => ['doop', 'snij'],
            'Sidal' => ['snij', 'doop'],
            'Boguslav' => ['doop'],
            'Arkadius' => ['doop'],
            'Laszlo' => ['snij'],
            'Aleyna' => ['snij'],
            'Nurseven' => ['snij'],
            'Daria' => ['snij'],
            'Nina' => ['snij'],
            'Aneta' => ['snij'],
            'Alibaba' => ['snij', 'doop'],
            'Mergyul' => ['snij', 'doop'],
            'Suleyman' => ['doop', 'draai'],
            'Jose' => ['deeg'],
            'Victor' => ['deeg', 'doop'],
            'Meral' => ['snij'],
            'Bahar' => ['doop'],
            'Mert' => [],
        ];

        foreach ($namedStaffStations as $name => $stationKeys) {
            $employee = $this->makeEmployee($name, 'Lijn3', $lijn3->id);
            $roleIds = collect($stationKeys)->map(fn (string $key) => $roles["lijn3_{$key}"]->id)->all();
            if ($roleIds !== []) {
                $employee->schedulingRoles()->syncWithoutDetaching($roleIds);
            }
        }

        // Agency staff observed as repeated unnamed "flex"/"VZM" rows on Lijn 3 - the
        // rooster has no individual names for these, just the agency label repeated across
        // several rows per week, so they're seeded as numbered agency placeholders.
        for ($i = 1; $i <= 4; $i++) {
            $employee = $this->makeAgencyEmployee("Flex {$i}", $lijn3->id, $flex);
            $employee->schedulingRoles()->syncWithoutDetaching([
                $roles['lijn3_doop']->id,
                $roles['lijn3_snij']->id,
            ]);
        }

        for ($i = 1; $i <= 2; $i++) {
            $employee = $this->makeAgencyEmployee("VZM {$i}", $lijn3->id, $vzm);
            $employee->schedulingRoles()->syncWithoutDetaching([$roles['lijn3_doop']->id]);
        }
    }

    private function makeEmployee(string $firstName, string $lastNameSuffix, ?int $defaultLineId): Employee
    {
        $email = strtolower($firstName).'.'.strtolower($lastNameSuffix).'@bakeryx.test';

        $employee = Employee::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastNameSuffix,
                'phone' => null,
                'employee_type' => 'vast',
                'agency_id' => null,
                'pay_type' => 'hourly',
                'hourly_rate' => 14.50,
                'monthly_salary' => null,
                'contracted_hours_per_week' => 40,
                'default_line_id' => $defaultLineId,
                'is_active' => true,
            ],
        );

        EmploymentTerm::updateOrCreate(
            ['employee_id' => $employee->id, 'effective_to' => null],
            ['max_weekly_hours' => 40, 'effective_from' => now()->startOfYear()],
        );

        return $employee;
    }

    private function makeAgencyEmployee(string $label, int $defaultLineId, Agency $agency): Employee
    {
        $email = strtolower(str_replace(' ', '.', $label)).'@bakeryx.test';

        $employee = Employee::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $label,
                'last_name' => $agency->name,
                'phone' => null,
                'employee_type' => 'uitzendkracht',
                'agency_id' => $agency->id,
                'pay_type' => 'hourly',
                'hourly_rate' => 16.00,
                'monthly_salary' => null,
                'contracted_hours_per_week' => 40,
                'default_line_id' => $defaultLineId,
                'is_active' => true,
            ],
        );

        EmploymentTerm::updateOrCreate(
            ['employee_id' => $employee->id, 'effective_to' => null],
            ['max_weekly_hours' => 40, 'effective_from' => now()->startOfYear()],
        );

        return $employee;
    }
}
