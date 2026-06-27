<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\AuthHelper;

class DepartmentTest extends TestCase
{
    use RefreshDatabase, AuthHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    private function departmentPayload(array $overrides = []): array
    {
        return array_merge([
            'name'        => 'Engineering',
            'code'        => 'ENG',
            'description' => 'Software development team',
        ], $overrides);
    }

    public function test_anyone_authenticated_can_list_departments(): void
    {
        $this->loginAs('employee');
        Department::factory(3)->create();

        $this->getJson('/api/departments')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_admin_can_create_department(): void
    {
        $this->loginAs('admin');

        $this->postJson('/api/departments', $this->departmentPayload())
            ->assertStatus(201)
            ->assertJsonFragment(['name' => 'Engineering', 'code' => 'ENG']);

        $this->assertDatabaseHas('departments', ['code' => 'ENG']);
    }

    public function test_hr_can_create_department(): void
    {
        $this->loginAs('hr');

        $this->postJson('/api/departments', $this->departmentPayload())
            ->assertStatus(201);
    }

    public function test_employee_cannot_create_department(): void
    {
        $this->loginAs('employee');

        $this->postJson('/api/departments', $this->departmentPayload())
            ->assertStatus(403);
    }

    public function test_can_view_single_department_with_positions(): void
    {
        $this->loginAs('admin');
        $department = Department::factory()->create();
        Position::factory(2)->create(['department_id' => $department->id]);

        $this->getJson("/api/departments/{$department->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'code', 'positions']]);
    }

    public function test_admin_can_update_department(): void
    {
        $this->loginAs('admin');
        $department = Department::factory()->create();

        $this->putJson("/api/departments/{$department->id}", [
            'name' => 'Updated Engineering',
        ])->assertOk()
            ->assertJsonFragment(['name' => 'Updated Engineering']);
    }

    public function test_employee_cannot_update_department(): void
    {
        $this->loginAs('employee');
        $department = Department::factory()->create();

        $this->putJson("/api/departments/{$department->id}", [
            'name' => 'Hacked',
        ])->assertStatus(403);
    }

    public function test_admin_can_delete_department(): void
    {
        $this->loginAs('admin');
        $department = Department::factory()->create();

        $this->deleteJson("/api/departments/{$department->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Department deleted.']);

        $this->assertDatabaseMissing('departments', ['id' => $department->id]);
    }

    public function test_cannot_delete_department_with_employees(): void
    {
        $this->loginAs('admin');
        $department = Department::factory()->create();
        $position   = Position::factory()->create(['department_id' => $department->id]);
        Employee::factory()->create([
            'department_id' => $department->id,
            'position_id'   => $position->id,
        ]);

        $this->deleteJson("/api/departments/{$department->id}")
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Cannot delete a department with existing employees.',
            ]);
    }

    public function test_employee_cannot_delete_department(): void
    {
        $this->loginAs('employee');
        $department = Department::factory()->create();

        $this->deleteJson("/api/departments/{$department->id}")
            ->assertStatus(403);
    }

    public function test_duplicate_department_code_is_rejected(): void
    {
        $this->loginAs('admin');
        Department::factory()->create(['code' => 'ENG']);

        $this->postJson('/api/departments', $this->departmentPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_create_department_validates_required_fields(): void
    {
        $this->loginAs('admin');

        $this->postJson('/api/departments', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code']);
    }
}
