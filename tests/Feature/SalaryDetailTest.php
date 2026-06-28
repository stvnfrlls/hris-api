<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SalaryDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\AuthHelper;

class SalaryDetailTest extends TestCase
{
    use RefreshDatabase, AuthHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    public function test_admin_can_list_salary_details(): void
    {
        $this->loginAs('admin');
        SalaryDetail::factory(3)->create();

        $this->getJson('/api/salary')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_hr_can_list_salary_details(): void
    {
        $this->loginAs('hr');
        SalaryDetail::factory(2)->create();

        $this->getJson('/api/salary')
            ->assertOk();
    }

    public function test_employee_cannot_list_all_salary_details(): void
    {
        $this->loginAs('employee');

        $this->getJson('/api/salary')
            ->assertStatus(403);
    }

    public function test_admin_can_create_salary_detail(): void
    {
        $this->loginAs('admin');
        $employee = Employee::factory()->create();

        $this->postJson('/api/salary', [
            'employee_id'  => $employee->id,
            'basic_salary' => 25000,
        ])->assertStatus(201)
            ->assertJsonFragment(['basic_salary' => '25000.00']);

        $this->assertDatabaseHas('salary_details', [
            'employee_id'  => $employee->id,
            'basic_salary' => 25000,
        ]);
    }

    public function test_hr_can_create_salary_detail(): void
    {
        $this->loginAs('hr');
        $employee = Employee::factory()->create();

        $this->postJson('/api/salary', [
            'employee_id'  => $employee->id,
            'basic_salary' => 20000,
        ])->assertStatus(201);
    }

    public function test_employee_cannot_create_salary_detail(): void
    {
        $this->loginAs('employee');
        $employee = Employee::factory()->create();

        $this->postJson('/api/salary', [
            'employee_id'  => $employee->id,
            'basic_salary' => 25000,
        ])->assertStatus(403);
    }

    public function test_duplicate_salary_detail_is_rejected(): void
    {
        $this->loginAs('admin');
        $salary = SalaryDetail::factory()->create();

        $this->postJson('/api/salary', [
            'employee_id'  => $salary->employee_id,
            'basic_salary' => 30000,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['employee_id']);
    }

    public function test_admin_can_view_employee_salary(): void
    {
        $this->loginAs('admin');
        $salary = SalaryDetail::factory()->create();

        $this->getJson("/api/salary/{$salary->employee_id}")
            ->assertOk()
            ->assertJsonFragment(['basic_salary' => number_format($salary->basic_salary, 2, '.', '')]);
    }

    public function test_admin_can_update_salary_detail(): void
    {
        $this->loginAs('admin');
        $salary = SalaryDetail::factory()->create();

        $this->putJson("/api/salary/{$salary->employee_id}", [
            'basic_salary' => 35000,
        ])->assertOk()
            ->assertJsonFragment(['basic_salary' => '35000.00']);
    }

    public function test_employee_cannot_update_salary_detail(): void
    {
        $this->loginAs('employee');
        $salary = SalaryDetail::factory()->create();

        $this->putJson("/api/salary/{$salary->employee_id}", [
            'basic_salary' => 99999,
        ])->assertStatus(403);
    }

    public function test_create_salary_validates_required_fields(): void
    {
        $this->loginAs('admin');

        $this->postJson('/api/salary', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['employee_id', 'basic_salary']);
    }

    public function test_basic_salary_must_be_numeric(): void
    {
        $this->loginAs('admin');
        $employee = Employee::factory()->create();

        $this->postJson('/api/salary', [
            'employee_id'  => $employee->id,
            'basic_salary' => 'not-a-number',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['basic_salary']);
    }
}
