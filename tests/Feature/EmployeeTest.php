<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthHelper;

class EmployeeTest extends TestCase
{
    use RefreshDatabase, AuthHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    private function makeDepartmentAndPosition(): array
    {
        $department = Department::factory()->create();
        $position   = Position::factory()->create([
            'department_id' => $department->id,
        ]);

        return [$department->id, $position->id];
    }

    private function employeePayload(int $userId, int $departmentId, int $positionId): array
    {
        return [
            'user_id'         => $userId,
            'employee_code'   => 'EMP-001',
            'department_id'   => $departmentId,
            'position_id'     => $positionId,
            'hire_date'       => '2024-01-15',
            'status'          => 'active',
        ];
    }

    public function test_admin_can_list_employees(): void
    {
        $this->loginAs('admin');
        Employee::factory(3)->create();

        $this->getJson('/api/employees')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_employee_role_cannot_list_all_employees(): void
    {
        $this->loginAs('employee');

        $this->getJson('/api/employees')
            ->assertStatus(403);
    }

    public function test_admin_can_create_employee(): void
    {
        $this->loginAs('admin');
        $targetUser = User::factory()->create();
        [$deptId, $posId] = $this->makeDepartmentAndPosition();

        $this->postJson('/api/employees', $this->employeePayload($targetUser->id, $deptId, $posId))
            ->assertStatus(201)
            ->assertJsonFragment([
                'employee_code' => 'EMP-001',
            ]);

        $this->assertDatabaseHas('employees', ['employee_code' => 'EMP-001']);
    }

    public function test_hr_can_create_employee(): void
    {
        $this->loginAs('hr');
        $targetUser = User::factory()->create();
        [$deptId, $posId] = $this->makeDepartmentAndPosition();

        $this->postJson('/api/employees', $this->employeePayload($targetUser->id, $deptId, $posId))
            ->assertStatus(201);
    }

    public function test_employee_role_cannot_create_employee(): void
    {
        $this->loginAs('employee');
        $targetUser = User::factory()->create();
        [$deptId, $posId] = $this->makeDepartmentAndPosition();

        $this->postJson('/api/employees', $this->employeePayload($targetUser->id, $deptId, $posId))
            ->assertStatus(403);
    }

    public function test_admin_can_view_single_employee(): void
    {
        $this->loginAs('admin');
        $employee = Employee::factory()->create();

        $this->getJson("/api/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $employee->id]);
    }

    public function test_employee_can_view_own_record(): void
    {
        $user = $this->loginAs('employee');
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $this->getJson("/api/employees/{$employee->id}")
            ->assertOk();
    }

    public function test_employee_cannot_view_other_employee_record(): void
    {
        $this->loginAs('employee');
        $otherEmployee = Employee::factory()->create();

        $this->getJson("/api/employees/{$otherEmployee->id}")
            ->assertStatus(403);
    }

    public function test_admin_can_update_employee(): void
    {
        $this->loginAs('admin');
        $employee = Employee::factory()->create();
        [$deptId, $posId] = $this->makeDepartmentAndPosition();

        $this->putJson("/api/employees/{$employee->id}", [
            'department_id' => $deptId,
            'position_id'   => $posId,
        ])->assertOk()
            ->assertJsonFragment(['id' => $employee->id]);
    }

    public function test_admin_can_delete_employee(): void
    {
        $this->loginAs('admin');
        $employee = Employee::factory()->create();

        $this->deleteJson("/api/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Employee deleted.']);

        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
    }

    public function test_create_employee_validates_required_fields(): void
    {
        $this->loginAs('admin');

        $this->postJson('/api/employees', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'user_id',
                'employee_code',
                'department_id',
                'position_id',
                'hire_date',
            ]);
    }

    public function test_duplicate_employee_code_is_rejected(): void
    {
        $this->loginAs('admin');
        Employee::factory()->create(['employee_code' => 'EMP-001']);
        $targetUser = User::factory()->create();
        [$deptId, $posId] = $this->makeDepartmentAndPosition();

        $this->postJson('/api/employees', $this->employeePayload($targetUser->id, $deptId, $posId))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['employee_code']);
    }
}
