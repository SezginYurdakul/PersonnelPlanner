<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsBakery;
use Tests\TestCase;

/**
 * An employee's qualified roles and, among their stations, the ones they prefer
 * (ProjectPlan.md §11a.3) - which the roster planner keeps them on (§28.2 tier 5).
 */
class EmployeeQualifiedRolesTest extends TestCase
{
    use BuildsBakery;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildBakery();
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_marks_the_preferred_stations_among_the_qualified_roles(): void
    {
        $ali = $this->people['Ali'];

        $this->actingAs($this->admin)->putJson("/api/v1/employees/{$ali->id}/qualified-roles", [
            'role_ids' => [$this->roles['dough']->id, $this->roles['oven']->id, $this->roles['machine']->id],
            'preferred_role_ids' => [$this->roles['machine']->id],
        ])->assertOk();

        $this->actingAs($this->admin)->getJson("/api/v1/employees/{$ali->id}")
            ->assertJsonPath('data.preferred_role_ids', [$this->roles['machine']->id]);
    }

    public function test_keeps_the_preferred_stations_when_they_are_left_out(): void
    {
        $ali = $this->people['Ali'];
        $ali->schedulingRoles()->updateExistingPivot($this->roles['oven']->id, ['is_preferred' => true]);

        $this->actingAs($this->admin)->putJson("/api/v1/employees/{$ali->id}/qualified-roles", [
            'role_ids' => [$this->roles['oven']->id, $this->roles['machine']->id],
        ])->assertOk();

        $this->assertSame([$this->roles['oven']->id], $ali->fresh()->preferredRoleIds()->all());
    }

    public function test_only_a_qualified_station_can_be_preferred(): void
    {
        $ali = $this->people['Ali'];

        $this->actingAs($this->admin)->putJson("/api/v1/employees/{$ali->id}/qualified-roles", [
            'role_ids' => [$this->roles['dough']->id],
            'preferred_role_ids' => [$this->roles['machine']->id],
        ])->assertJsonValidationErrors('preferred_role_ids.0');

        $this->actingAs($this->admin)->putJson("/api/v1/employees/{$ali->id}/qualified-roles", [
            'role_ids' => [$this->roles['dough']->id, $this->roles['silo']->id],
            'preferred_role_ids' => [$this->roles['silo']->id],
        ])->assertJsonValidationErrors('preferred_role_ids.0');
    }
}
