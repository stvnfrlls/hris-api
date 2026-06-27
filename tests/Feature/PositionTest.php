<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\AuthHelper;

class PositionTest extends TestCase
{
    use RefreshDatabase, AuthHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    private function positionPayload(int $departmentId, array $overrides = []): array
    {
        return array_merge([
            'department_id'   => $departmentId,
            'name'            => 'Backend Developer',
            'employment_type' => 'full_time',
        ], $overrides);
    }

    public function test_anyone_authenticated_can_list_positions(): void
    {
        $this->loginAs('employee');
        Position::factory(3)->create();

        $this->getJson('/api/positions')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_can_filter_positions_by_department(): void
    {
        $this->loginAs('admin');
        $dept1 = Department::factory()->create();
        $dept2 = Department::factory()->create();

        Position::factory(2)->create(['department_id' => $dept1->id]);
        Position::factory(3)->create(['department_id' => $dept2->id]);

        $this->getJson("/api/positions?department_id={$dept1->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_positions_by_employment_type(): void
    {
        $this->loginAs('admin');
        Position::factory(2)->create(['employment_type' => 'full_time']);
        Position::factory(2)->create(['employment_type' => 'part_time']);

        $this->getJson('/api/positions?employment_type=full_time')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_position(): void
    {
        $this->loginAs('admin');
        $department = Department::factory()->create();

        $this->postJson('/api/positions', $this->positionPayload($department->id))
            ->assertStatus(201)
            ->assertJsonFragment(['name' => 'Backend Developer']);

        $this->assertDatabaseHas('positions', [
            'name'          => 'Backend Developer',
            'department_id' => $department->id,
        ]);
    }

    public function test_hr_can_create_position(): void
    {
        $this->loginAs('hr');
        $department = Department::factory()->create();

        $this->postJson('/api/positions', $this->positionPayload($department->id))
            ->assertStatus(201);
    }

    public function test_employee_cannot_create_position(): void
    {
        $this->loginAs('employee');
        $department = Department::factory()->create();

        $this->postJson('/api/positions', $this->positionPayload($department->id))
            ->assertStatus(403);
    }

    public function test_can_view_single_position(): void
    {
        $this->loginAs('admin');
        $position = Position::factory()->create();

        $this->getJson("/api/positions/{$position->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'employment_type', 'department']]);
    }

    public function test_admin_can_update_position(): void
    {
        $this->loginAs('admin');
        $position = Position::factory()->create();

        $this->putJson("/api/positions/{$position->id}", [
            'name' => 'Senior Backend Developer',
        ])->assertOk()
            ->assertJsonFragment(['name' => 'Senior Backend Developer']);
    }

    public function test_employee_cannot_update_position(): void
    {
        $this->loginAs('employee');
        $position = Position::factory()->create();

        $this->putJson("/api/positions/{$position->id}", [
            'name' => 'Hacked',
        ])->assertStatus(403);
    }

    public function test_admin_can_delete_position(): void
    {
        $this->loginAs('admin');
        $position = Position::factory()->create();

        $this->deleteJson("/api/positions/{$position->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Position deleted.']);

        $this->assertDatabaseMissing('positions', ['id' => $position->id]);
    }

    public function test_cannot_delete_position_with_employees(): void
    {
        $this->loginAs('admin');
        $position = Position::factory()->create();
        Employee::factory()->create([
            'department_id' => $position->department_id,
            'position_id'   => $position->id,
        ]);

        $this->deleteJson("/api/positions/{$position->id}")
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Cannot delete a position with existing employees.',
            ]);
    }

    public function test_create_position_validates_required_fields(): void
    {
        $this->loginAs('admin');

        $this->postJson('/api/positions', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['department_id', 'name', 'employment_type']);
    }

    public function test_create_position_requires_valid_department(): void
    {
        $this->loginAs('admin');

        $this->postJson('/api/positions', [
            'department_id'   => 9999,
            'name'            => 'Ghost Position',
            'employment_type' => 'full_time',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['department_id']);
    }
}
