<?php

namespace Tests\Feature;

use App\Models\PayrollPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\AuthHelper;

class PayrollPeriodTest extends TestCase
{
    use RefreshDatabase, AuthHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    private function periodPayload(array $overrides = []): array
    {
        return array_merge([
            'month'       => 6,
            'year'        => now()->year,
            'period_type' => 'first_half',
        ], $overrides);
    }

    public function test_admin_can_list_payroll_periods(): void
    {
        $this->loginAs('admin');
        PayrollPeriod::factory(3)->create();

        $this->getJson('/api/payroll-periods')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_admin_can_create_payroll_period(): void
    {
        $this->loginAs('admin');

        $this->postJson('/api/payroll-periods', $this->periodPayload())
            ->assertStatus(201)
            ->assertJsonFragment([
                'period_type' => 'first_half',
                'month'       => 6,
            ]);

        $this->assertDatabaseHas('payroll_periods', [
            'month'       => 6,
            'period_type' => 'first_half',
        ]);
    }

    public function test_first_half_period_dates_are_set_correctly(): void
    {
        $this->loginAs('admin');

        $this->postJson('/api/payroll-periods', $this->periodPayload([
            'month' => 6,
            'year'  => 2026,
        ]))->assertStatus(201)
            ->assertJsonFragment([
                'start_date' => '2026-06-01',
                'end_date'   => '2026-06-15',
            ]);
    }

    public function test_second_half_period_dates_are_set_correctly(): void
    {
        $this->loginAs('admin');

        $this->postJson('/api/payroll-periods', $this->periodPayload([
            'month'       => 6,
            'year'        => 2026,
            'period_type' => 'second_half',
        ]))->assertStatus(201)
            ->assertJsonFragment([
                'start_date' => '2026-06-16',
                'end_date'   => '2026-06-30',
            ]);
    }

    public function test_hr_cannot_create_payroll_period(): void
    {
        $this->loginAs('hr');

        $this->postJson('/api/payroll-periods', $this->periodPayload())
            ->assertStatus(403);
    }

    public function test_employee_cannot_create_payroll_period(): void
    {
        $this->loginAs('employee');

        $this->postJson('/api/payroll-periods', $this->periodPayload())
            ->assertStatus(403);
    }

    public function test_duplicate_period_is_rejected(): void
    {
        $this->loginAs('admin');
        PayrollPeriod::factory()->create([
            'month'       => 6,
            'year'        => now()->year,
            'period_type' => 'first_half',
        ]);

        $this->postJson('/api/payroll-periods', $this->periodPayload())
            ->assertStatus(422);
    }

    public function test_admin_can_view_payroll_period(): void
    {
        $this->loginAs('admin');
        $period = PayrollPeriod::factory()->create();

        $this->getJson("/api/payroll-periods/{$period->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $period->id]);
    }

    public function test_create_period_validates_required_fields(): void
    {
        $this->loginAs('admin');

        $this->postJson('/api/payroll-periods', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month', 'year', 'period_type']);
    }
}
