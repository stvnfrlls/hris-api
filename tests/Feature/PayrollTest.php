<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\SalaryDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\AuthHelper;

class PayrollTest extends TestCase
{
    use RefreshDatabase, AuthHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    private function setupEmployeeWithSalary(User $user, float $salary = 25000): array
    {
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $salaryDetail = SalaryDetail::factory()->create([
            'employee_id'  => $employee->id,
            'basic_salary' => $salary,
        ]);

        return [$employee, $salaryDetail];
    }

    private function seedAttendance(Employee $employee, PayrollPeriod $period, int $days = 10): void
    {
        $date = $period->start_date->copy();

        for ($i = 0; $i < $days; $i++) {
            if ($date->isWeekday()) {
                Attendance::factory()->create([
                    'employee_id' => $employee->id,
                    'date'        => $date->toDateString(),
                    'clock_in'    => '08:00:00',
                    'clock_out'   => '17:00:00',
                    'status'      => 'present',
                ]);
            }
            $date->addDay();
        }
    }

    public function test_admin_can_generate_payroll(): void
    {
        $user            = $this->loginAs('admin');
        [$employee]      = $this->setupEmployeeWithSalary($user);
        $period          = PayrollPeriod::factory()->create(['status' => 'draft']);

        $this->seedAttendance($employee, $period);

        $this->postJson('/api/payroll/generate', [
            'payroll_period_id' => $period->id,
        ])->assertOk()
            ->assertJsonStructure(['message', 'count']);

        $this->assertDatabaseHas('payrolls', [
            'employee_id'       => $employee->id,
            'payroll_period_id' => $period->id,
        ]);
    }

    public function test_hr_cannot_generate_payroll(): void
    {
        $this->loginAs('hr');
        $period = PayrollPeriod::factory()->create();

        $this->postJson('/api/payroll/generate', [
            'payroll_period_id' => $period->id,
        ])->assertStatus(403);
    }

    public function test_employee_cannot_generate_payroll(): void
    {
        $this->loginAs('employee');
        $period = PayrollPeriod::factory()->create();

        $this->postJson('/api/payroll/generate', [
            'payroll_period_id' => $period->id,
        ])->assertStatus(403);
    }

    public function test_cannot_regenerate_released_period(): void
    {
        $this->loginAs('admin');
        $period = PayrollPeriod::factory()->create(['status' => 'released']);

        $this->postJson('/api/payroll/generate', [
            'payroll_period_id' => $period->id,
        ])->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Cannot regenerate a released payroll period.',
            ]);
    }

    public function test_employees_without_salary_are_skipped_during_generation(): void
    {
        $this->loginAs('admin');
        Employee::factory(2)->create(); // no salary details
        $period = PayrollPeriod::factory()->create(['status' => 'draft']);

        $this->postJson('/api/payroll/generate', [
            'payroll_period_id' => $period->id,
        ])->assertOk()
            ->assertJsonFragment(['count' => 0]);
    }

    public function test_payroll_deductions_are_created_on_generation(): void
    {
        $user       = $this->loginAs('admin');
        [$employee] = $this->setupEmployeeWithSalary($user);
        $period     = PayrollPeriod::factory()->create(['status' => 'draft']);

        $this->seedAttendance($employee, $period);

        $this->postJson('/api/payroll/generate', [
            'payroll_period_id' => $period->id,
        ])->assertOk();

        $payroll = Payroll::where('employee_id', $employee->id)
            ->where('payroll_period_id', $period->id)
            ->first();

        $this->assertNotNull($payroll);
        $this->assertGreaterThan(0, $payroll->deductions()->count());
    }

    public function test_net_pay_is_less_than_gross_pay(): void
    {
        $user       = $this->loginAs('admin');
        [$employee] = $this->setupEmployeeWithSalary($user, 25000);
        $period     = PayrollPeriod::factory()->create(['status' => 'draft']);

        $this->seedAttendance($employee, $period);

        $this->postJson('/api/payroll/generate', [
            'payroll_period_id' => $period->id,
        ])->assertOk();

        $payroll = Payroll::where('employee_id', $employee->id)->first();

        $this->assertLessThan($payroll->gross_pay, $payroll->net_pay);
    }

    public function test_admin_can_view_all_payrolls(): void
    {
        $this->loginAs('admin');
        Payroll::factory(5)->create();

        $this->getJson('/api/payroll')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_employee_can_view_own_payroll_only(): void
    {
        $user     = $this->loginAs('employee');
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $period1 = PayrollPeriod::factory()->create([
            'month' => now()->month,
            'year' => now()->year,
            'period_type' => 'first_half',
        ]);
        $period2 = PayrollPeriod::factory()->create([
            'month' => now()->month,
            'year' => now()->year,
            'period_type' => 'second_half',
        ]);

        Payroll::factory()->create(['employee_id' => $employee->id, 'payroll_period_id' => $period1->id]);
        Payroll::factory()->create(['employee_id' => $employee->id, 'payroll_period_id' => $period2->id]);
        Payroll::factory(2)->create(); // other employees — will use shared period via factory

        $this->getJson('/api/payroll')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_filter_payroll_by_period(): void
    {
        $this->loginAs('admin');
        $period1 = PayrollPeriod::factory()->create();
        $period2 = PayrollPeriod::factory()->create();

        Payroll::factory(3)->create(['payroll_period_id' => $period1->id]);
        Payroll::factory(2)->create(['payroll_period_id' => $period2->id]);

        $this->getJson("/api/payroll?period_id={$period1->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_view_single_payroll(): void
    {
        $this->loginAs('admin');
        $payroll = Payroll::factory()->create();

        $this->getJson("/api/payroll/{$payroll->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'basic_salary',
                    'days_worked',
                    'gross_pay',
                    'total_deductions',
                    'net_pay',
                    'status',
                    'employee',
                    'period',
                    'deductions',
                ],
            ]);
    }

    public function test_employee_cannot_view_other_employee_payroll(): void
    {
        $user     = $this->loginAs('employee');
        Employee::factory()->create(['user_id' => $user->id]);
        $otherPayroll = Payroll::factory()->create();

        $this->getJson("/api/payroll/{$otherPayroll->id}")
            ->assertStatus(403);
    }

    public function test_admin_can_release_payroll(): void
    {
        $this->loginAs('admin');
        $payroll = Payroll::factory()->create(['status' => 'draft']);

        $this->postJson("/api/payroll/{$payroll->id}/release")
            ->assertOk()
            ->assertJsonFragment(['status' => 'released']);

        $this->assertDatabaseHas('payrolls', [
            'id'     => $payroll->id,
            'status' => 'released',
        ]);
    }

    public function test_cannot_release_already_released_payroll(): void
    {
        $this->loginAs('admin');
        $payroll = Payroll::factory()->create(['status' => 'released']);

        $this->postJson("/api/payroll/{$payroll->id}/release")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Payroll already released.']);
    }

    public function test_hr_cannot_release_payroll(): void
    {
        $this->loginAs('hr');
        $payroll = Payroll::factory()->create(['status' => 'draft']);

        $this->postJson("/api/payroll/{$payroll->id}/release")
            ->assertStatus(403);
    }

    public function test_generate_payroll_validates_required_fields(): void
    {
        $this->loginAs('admin');

        $this->postJson('/api/payroll/generate', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payroll_period_id']);
    }
}
