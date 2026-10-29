<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Lines\Models\Line;
use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Staff\Models\Agency;
use App\Modules\Staff\Models\Employee;
use App\Modules\Staff\Models\EmploymentTerm;
use Illuminate\Database\Seeder;

/**
 * Test data reconstructed from the real customer's paper rooster (Excel screenshots, weeks
 * 31-39, July-September 2026) - real employee names, real line assignments, real station
 * labels observed on each line. Built for testing the Weekly Scheduling module (Phase 5)
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
        $this->createLijn2Employees($lines['lijn2'], $roles);
        $this->createLijn3Employees($lines['lijn3'], $roles, $agencies['flex'], $agencies['vzm']);

        $this->call(BakeryXHistoricalRosterSeeder::class);
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

        // Lijn 1 stations: dough prep, mid-line shaping/panning + tiger pap, and
        // decoration/lid/tray-swap at the end of the line.
        $roles['lijn1_deeg'] = SchedulingRole::updateOrCreate(
            ['name' => 'Deeg Voorbereiding (Lijn 1)', 'line_id' => $lines['lijn1']->id],
            ['role_kind' => SchedulingRole::KIND_STATION, 'requires_coverage' => true, 'is_active' => true],
        );
        $roles['lijn1_shaping'] = SchedulingRole::updateOrCreate(
            ['name' => 'Vormen & Tiger Pap (Lijn 1)', 'line_id' => $lines['lijn1']->id],
            ['role_kind' => SchedulingRole::KIND_STATION, 'requires_coverage' => true, 'is_active' => true],
        );
        $roles['lijn1_decoration'] = SchedulingRole::updateOrCreate(
            ['name' => 'Decoratie & Deksel (Lijn 1)', 'line_id' => $lines['lijn1']->id],
            ['role_kind' => SchedulingRole::KIND_STATION, 'requires_coverage' => true, 'is_active' => true],
        );

        // Lijn 1 secondary tasks - attached to the whole line (not one specific station),
        // per the confirmed design: whoever is on shift on Lijn 1 can pick these up.
        $roles['lijn1_tiger_pap_prep'] = SchedulingRole::updateOrCreate(
            ['name' => 'Tiger Pap Voorbereiden (Lijn 1)', 'line_id' => $lines['lijn1']->id],
            ['role_kind' => SchedulingRole::KIND_SECONDARY_TASK, 'attachment_type' => 'line', 'requires_coverage' => false, 'is_active' => true],
        );
        $roles['lijn1_robot'] = SchedulingRole::updateOrCreate(
            ['name' => 'Robot & Tray Instellen (Lijn 1)', 'line_id' => $lines['lijn1']->id],
            ['role_kind' => SchedulingRole::KIND_SECONDARY_TASK, 'attachment_type' => 'line', 'requires_coverage' => false, 'is_active' => true],
        );

        // Lijn 2: a single operator station (egg-wash refresh + machine cleaning tracking).
        $roles['lijn2_operator'] = SchedulingRole::updateOrCreate(
            ['name' => 'Lijn 2 Operator', 'line_id' => $lines['lijn2']->id],
            ['role_kind' => SchedulingRole::KIND_STATION, 'requires_coverage' => true, 'is_active' => true],
        );

        // Lijn 3 station labels observed directly in the rooster cells (draai/snij/doop/deeg),
        // plus a fifth station for the oven-exit hand-off to the belt.
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

        $roles['lijn3_ovenexit'] = SchedulingRole::updateOrCreate(
            ['name' => 'Ovenuitgang (Lijn 3)', 'line_id' => $lines['lijn3']->id],
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
     * Leiding (shift leaders) each get a linked User account: Menno is `super_admin` (the
     * sole review/approve authority per the schedule-approval policy), the rest are
     * `admin`. Every account uses the shared test password.
     *
     * @param  array<string, SchedulingRole>  $roles
     */
    private function createLeidingEmployees(array $roles): void
    {
        $names = ['Menno', 'Sander', 'Mark', 'Marc', 'Erdal', 'Winfred', 'Kars'];

        foreach ($names as $name) {
            $employee = $this->makeEmployee($name, 'Leiding', null);
            $employee->schedulingRoles()->syncWithoutDetaching([$roles['silo_watch']->id, $roles['3pl_desem']->id]);

            $role = $name === 'Menno' ? 'super_admin' : 'admin';
            $user = User::updateOrCreate(
                ['email' => strtolower($name).'@bakeryx.test'],
                [
                    'name' => $name,
                    'password' => 'password',
                    'visibility_scope' => 'company',
                    'locale' => 'nl',
                    'is_active' => true,
                ],
            );
            $user->syncRoles([$role]);
            $employee->update(['user_id' => $user->id]);
        }
    }

    /**
     * @param  array<string, SchedulingRole>  $roles
     */
    private function createLijn1Employees(Line $lijn1, array $roles): void
    {
        $names = ['Roland', 'David', 'Jaco', 'Hendrick', 'Sezgin', 'Kevin', 'Erik'];
        $lijn1RoleIds = [
            $roles['lijn1_deeg']->id,
            $roles['lijn1_shaping']->id,
            $roles['lijn1_decoration']->id,
            $roles['lijn1_tiger_pap_prep']->id,
            $roles['lijn1_robot']->id,
        ];

        foreach ($names as $name) {
            $employee = $this->makeEmployee($name, 'Lijn1', $lijn1->id);
            // Every Lijn 1 employee can perform every Lijn 1 role, per the confirmed
            // "assign everyone as able to do every job on their line" scope.
            $employee->schedulingRoles()->syncWithoutDetaching($lijn1RoleIds);

            if ($name === 'Sezgin') {
                $this->linkRegularUser($employee, $name);
            }
        }
    }

    /**
     * Gives one floor employee a plain `user` account (own-scope visibility) so the
     * employee self-service side can be tested without an invite round-trip.
     */
    private function linkRegularUser(Employee $employee, string $name): void
    {
        $user = User::updateOrCreate(
            ['email' => strtolower($name).'@bakeryx.test'],
            [
                'name' => $name,
                'password' => 'password',
                'visibility_scope' => 'own',
                'locale' => 'nl',
                'is_active' => true,
                'activated_at' => now(),
            ],
        );
        $user->syncRoles(['user']);
        $employee->update(['user_id' => $user->id]);
    }

    /**
     * @param  array<string, SchedulingRole>  $roles
     */
    private function createLijn2Employees(Line $lijn2, array $roles): void
    {
        $names = ['Ali', 'Ruud', 'Berkhli', 'Serkan', 'Carlos', 'Flavio'];
        $lijn2Operator = $roles['lijn2_operator'];

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
        // Named permanent staff observed on Lijn 3 across the rooster screenshots. Every
        // Lijn 3 employee is qualified for every Lijn 3 station, per the confirmed "assign
        // everyone as able to do every job on their line" scope.
        $names = [
            'Frank', 'Nicusor', 'Mustafa', 'Luca', 'Jan', 'Sem', 'Justin', 'Tanja', 'Mahamud',
            'Ayshe', 'Arda', 'Sidal', 'Boguslav', 'Arkadius', 'Laszlo', 'Aleyna', 'Nurseven',
            'Daria', 'Nina', 'Aneta', 'Alibaba', 'Mergyul', 'Suleyman', 'Jose', 'Victor',
            'Meral', 'Bahar', 'Mert', 'Gabriela', 'Patrick',
        ];

        $lijn3RoleIds = [
            $roles['lijn3_deeg']->id,
            $roles['lijn3_draai']->id,
            $roles['lijn3_doop']->id,
            $roles['lijn3_snij']->id,
            $roles['lijn3_ovenexit']->id,
        ];

        foreach ($names as $name) {
            $employee = $this->makeEmployee($name, 'Lijn3', $lijn3->id);
            $employee->schedulingRoles()->syncWithoutDetaching($lijn3RoleIds);
        }

        // Agency staff observed as repeated unnamed "flex"/"VZM" rows on Lijn 3 - the
        // rooster has no individual names for these, just the agency label repeated across
        // several rows per week, so they're seeded as numbered agency placeholders.
        for ($i = 1; $i <= 4; $i++) {
            $employee = $this->makeAgencyEmployee("Flex {$i}", $lijn3->id, $flex);
            $employee->schedulingRoles()->syncWithoutDetaching($lijn3RoleIds);
        }

        for ($i = 1; $i <= 2; $i++) {
            $employee = $this->makeAgencyEmployee("VZM {$i}", $lijn3->id, $vzm);
            $employee->schedulingRoles()->syncWithoutDetaching($lijn3RoleIds);
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
