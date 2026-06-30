<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\SalaryDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthHelper;

class ReportTest extends TestCase
{
    use RefreshDatabase, AuthHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    // ----------------------------
    // Attendance Summary
    // ----------------------------

    public function test_admin_can_view_attendance_summary(): void
    {
        $this->loginAs('admin');
        $employee = Employee::factory()->create();

        Attendance::factory()->create([
            'employee_id' => $employee->id,
            'date'        => now()->startOfMonth()->addDays(1),
            'status'      => 'present',
        ]);

        Attendance::factory()->create([
            'employee_id' => $employee->id,
            'date'        => now()->startOfMonth()->addDays(2),
            'status'      => 'late',
        ]);

        $this->getJson('/api/reports/attendance-summary?month=' . now()->month . '&year=' . now()->year)
            ->assertOk()
            ->assertJsonStructure([
                'period' => ['month', 'year', 'start_date', 'end_date'],
                'data',
                'totals' => ['total_present', 'total_late', 'total_absent', 'total_half_day'],
            ]);
    }

    public function test_attendance_summary_counts_are_correct(): void
    {
        $this->loginAs('admin');
        $employee = Employee::factory()->create();

        Attendance::factory(3)
            ->sequence(
                ['date' => now()->startOfMonth()->addDays(1)->toDateString()],
                ['date' => now()->startOfMonth()->addDays(2)->toDateString()],
                ['date' => now()->startOfMonth()->addDays(3)->toDateString()],
            )
            ->create([
                'employee_id' => $employee->id,
                'status'      => 'present',
            ]);

        $this->getJson('/api/reports/attendance-summary?month=' . now()->month . '&year=' . now()->year)
            ->assertOk()
            ->assertJsonFragment(['total_present' => 3]);
    }

    public function test_hr_can_view_attendance_summary(): void
    {
        $this->loginAs('hr');

        $this->getJson('/api/reports/attendance-summary')
            ->assertOk();
    }

    public function test_employee_cannot_view_attendance_summary(): void
    {
        $this->loginAs('employee');

        $this->getJson('/api/reports/attendance-summary')
            ->assertStatus(403);
    }

    public function test_attendance_summary_defaults_to_current_month(): void
    {
        $this->loginAs('admin');

        $this->getJson('/api/reports/attendance-summary')
            ->assertOk()
            ->assertJsonFragment([
                'month' => now()->month,
                'year'  => now()->year,
            ]);
    }

    // ----------------------------
    // Tardiness
    // ----------------------------

    public function test_admin_can_view_tardiness_report(): void
    {
        $this->loginAs('admin');
        $employee = Employee::factory()->create();

        Attendance::factory(2)
            ->sequence(
                ['date' => now()->startOfMonth()->addDays(1)->toDateString()],
                ['date' => now()->startOfMonth()->addDays(2)->toDateString()],
            )
            ->create([
                'employee_id' => $employee->id,
                'status'      => 'late',
            ]);

        $this->getJson('/api/reports/tardiness?month=' . now()->month . '&year=' . now()->year)
            ->assertOk()
            ->assertJsonFragment(['late_count' => 2]);
    }

    public function test_tardiness_excludes_employees_with_zero_late_count(): void
    {
        $this->loginAs('admin');
        $employee = Employee::factory()->create();

        Attendance::factory()->create([
            'employee_id' => $employee->id,
            'date'        => now()->startOfMonth()->addDays(1),
            'status'      => 'present',
        ]);

        $this->getJson('/api/reports/tardiness?month=' . now()->month . '&year=' . now()->year)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_tardiness_is_sorted_descending(): void
    {
        $this->loginAs('admin');

        $employeeA = Employee::factory()->create();
        $employeeB = Employee::factory()->create();

        Attendance::factory(1)->create([
            'employee_id' => $employeeA->id,
            'date'        => now()->startOfMonth()->addDays(1)->toDateString(),
            'status'      => 'late',
        ]);

        Attendance::factory(3)
            ->sequence(
                ['date' => now()->startOfMonth()->addDays(2)->toDateString()],
                ['date' => now()->startOfMonth()->addDays(3)->toDateString()],
                ['date' => now()->startOfMonth()->addDays(4)->toDateString()],
            )
            ->create([
                'employee_id' => $employeeB->id,
                'status'      => 'late',
            ]);

        $response = $this->getJson('/api/reports/tardiness?month=' . now()->month . '&year=' . now()->year)
            ->assertOk()
            ->json('data');

        $this->assertEquals($employeeB->id, $response[0]['employee_id']);
        $this->assertEquals(3, $response[0]['late_count']);
    }

    public function test_employee_cannot_view_tardiness_report(): void
    {
        $this->loginAs('employee');

        $this->getJson('/api/reports/tardiness')
            ->assertStatus(403);
    }

    // ----------------------------
    // Payroll Summary
    // ----------------------------

    public function test_admin_can_view_payroll_summary(): void
    {
        $this->loginAs('admin');

        $department = Department::factory()->create();
        $position   = Position::factory()->create(['department_id' => $department->id]);
        $employee   = Employee::factory()->create([
            'department_id' => $department->id,
            'position_id'   => $position->id,
        ]);

        $period = PayrollPeriod::factory()->create([
            'month' => now()->month,
            'year'  => now()->year,
        ]);

        Payroll::factory()->create([
            'employee_id'       => $employee->id,
            'payroll_period_id' => $period->id,
            'gross_pay'         => 12500,
            'total_deductions'  => 1000,
            'net_pay'           => 11500,
        ]);

        $this->getJson('/api/reports/payroll-summary')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'totals' => ['employee_count', 'total_gross_pay', 'total_deductions', 'total_net_pay'],
            ]);
    }

    public function test_payroll_summary_groups_by_department(): void
    {
        $this->loginAs('admin');

        $deptA = Department::factory()->create(['name' => 'Engineering']);
        $deptB = Department::factory()->create(['name' => 'HR']);

        $posA = Position::factory()->create(['department_id' => $deptA->id]);
        $posB = Position::factory()->create(['department_id' => $deptB->id]);

        $empA = Employee::factory()->create(['department_id' => $deptA->id, 'position_id' => $posA->id]);
        $empB = Employee::factory()->create(['department_id' => $deptB->id, 'position_id' => $posB->id]);

        $period = PayrollPeriod::factory()->create([
            'month' => now()->month,
            'year'  => now()->year,
        ]);

        Payroll::factory()->create([
            'employee_id'       => $empA->id,
            'payroll_period_id' => $period->id,
            'gross_pay'         => 12500,
        ]);

        Payroll::factory()->create([
            'employee_id'       => $empB->id,
            'payroll_period_id' => $period->id,
            'gross_pay'         => 10000,
        ]);

        $this->getJson('/api/reports/payroll-summary')
            ->assertOk()
            ->assertJsonFragment(['department' => 'Engineering'])
            ->assertJsonFragment(['department' => 'HR']);
    }

    public function test_payroll_summary_can_filter_by_specific_period(): void
    {
        $this->loginAs('admin');

        $period1 = PayrollPeriod::factory()->create();
        $period2 = PayrollPeriod::factory()->create();

        Payroll::factory(2)->create(['payroll_period_id' => $period1->id]);
        Payroll::factory(3)->create(['payroll_period_id' => $period2->id]);

        $this->getJson("/api/reports/payroll-summary?payroll_period_id={$period1->id}")
            ->assertOk()
            ->assertJsonFragment(['employee_count' => 2]);
    }

    public function test_hr_can_view_payroll_summary(): void
    {
        $this->loginAs('hr');

        $this->getJson('/api/reports/payroll-summary')
            ->assertOk();
    }

    public function test_employee_cannot_view_payroll_summary(): void
    {
        $this->loginAs('employee');

        $this->getJson('/api/reports/payroll-summary')
            ->assertStatus(403);
    }

    // ----------------------------
    // Headcount
    // ----------------------------

    public function test_admin_can_view_headcount_report(): void
    {
        $this->loginAs('admin');

        $department = Department::factory()->create();
        $position   = Position::factory()->create([
            'department_id'   => $department->id,
            'employment_type' => 'full_time',
        ]);

        Employee::factory(3)->create([
            'department_id' => $department->id,
            'position_id'   => $position->id,
            'status'        => 'active',
        ]);

        $this->getJson('/api/reports/headcount')
            ->assertOk()
            ->assertJsonStructure([
                'by_department',
                'by_employment_type',
                'by_status',
                'total_employees',
            ])
            ->assertJsonFragment(['total_employees' => 3]);
    }

    public function test_headcount_groups_by_department_correctly(): void
    {
        $this->loginAs('admin');

        $department = Department::factory()->create(['name' => 'Engineering']);
        $position   = Position::factory()->create(['department_id' => $department->id]);

        Employee::factory(4)->create([
            'department_id' => $department->id,
            'position_id'   => $position->id,
        ]);

        $this->getJson('/api/reports/headcount')
            ->assertOk()
            ->assertJsonFragment([
                'department' => 'Engineering',
                'count'      => 4,
            ]);
    }

    public function test_headcount_groups_by_employment_type(): void
    {
        $this->loginAs('admin');

        $department = Department::factory()->create();
        $fullTimePos = Position::factory()->create([
            'department_id'   => $department->id,
            'employment_type' => 'full_time',
        ]);
        $partTimePos = Position::factory()->create([
            'department_id'   => $department->id,
            'employment_type' => 'part_time',
        ]);

        Employee::factory(2)->create([
            'department_id' => $department->id,
            'position_id'   => $fullTimePos->id,
        ]);

        Employee::factory()->create([
            'department_id' => $department->id,
            'position_id'   => $partTimePos->id,
        ]);

        $this->getJson('/api/reports/headcount')
            ->assertOk()
            ->assertJsonFragment(['employment_type' => 'full_time', 'count' => 2])
            ->assertJsonFragment(['employment_type' => 'part_time', 'count' => 1]);
    }

    public function test_headcount_groups_by_status(): void
    {
        $this->loginAs('admin');

        $department = Department::factory()->create();
        $position   = Position::factory()->create(['department_id' => $department->id]);

        Employee::factory(2)->create([
            'department_id' => $department->id,
            'position_id'   => $position->id,
            'status'        => 'active',
        ]);

        Employee::factory()->create([
            'department_id' => $department->id,
            'position_id'   => $position->id,
            'status'        => 'inactive',
        ]);

        $this->getJson('/api/reports/headcount')
            ->assertOk()
            ->assertJsonFragment(['status' => 'active', 'count' => 2])
            ->assertJsonFragment(['status' => 'inactive', 'count' => 1]);
    }

    public function test_hr_can_view_headcount_report(): void
    {
        $this->loginAs('hr');

        $this->getJson('/api/reports/headcount')
            ->assertOk();
    }

    public function test_employee_cannot_view_headcount_report(): void
    {
        $this->loginAs('employee');

        $this->getJson('/api/reports/headcount')
            ->assertStatus(403);
    }
}
