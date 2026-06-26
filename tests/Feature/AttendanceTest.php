<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthHelper;

class AttendanceTest extends TestCase
{
    use RefreshDatabase, AuthHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    private function createEmployeeForUser(User $user): Employee
    {
        return Employee::factory()->create(['user_id' => $user->id]);
    }

    public function test_employee_can_clock_in(): void
    {
        $user = $this->loginAs('employee');
        $this->createEmployeeForUser($user);

        $this->travelTo(now()->setTimeFromTimeString('08:00:00'));

        $this->postJson('/api/attendance/clock-in')
            ->assertStatus(201)
            ->assertJsonFragment(['status' => 'present']);

        $this->assertDatabaseHas('attendances', [
            'date' => now()->toDateString(),
        ]);
    }

    public function test_employee_cannot_clock_in_twice(): void
    {
        $user     = $this->loginAs('employee');
        $employee = $this->createEmployeeForUser($user);

        Attendance::factory()->create([
            'employee_id' => $employee->id,
            'date'        => now()->toDateString(),
            'clock_in'    => '08:00:00',
        ]);

        $this->postJson('/api/attendance/clock-in')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Already clocked in today.']);
    }

    public function test_employee_can_clock_out(): void
    {
        $user     = $this->loginAs('employee');
        $employee = $this->createEmployeeForUser($user);

        Attendance::factory()->create([
            'employee_id' => $employee->id,
            'date'        => now()->toDateString(),
            'clock_in'    => '08:00:00',
            'clock_out'   => null,
        ]);

        $this->postJson('/api/attendance/clock-out')
            ->assertOk()
            ->assertJsonPath('data.clock_out', now()->format('H:i'));
    }

    public function test_employee_cannot_clock_out_without_clocking_in(): void
    {
        $user     = $this->loginAs('employee');
        $employee = $this->createEmployeeForUser($user);

        Attendance::factory()->create([
            'employee_id' => $employee->id,
            'date'        => now()->toDateString(),
            'clock_in'    => null,
        ]);

        $this->postJson('/api/attendance/clock-out')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'You have not clocked in yet.']);
    }

    public function test_employee_cannot_clock_out_twice(): void
    {
        $user     = $this->loginAs('employee');
        $employee = $this->createEmployeeForUser($user);

        Attendance::factory()->create([
            'employee_id' => $employee->id,
            'date'        => now()->toDateString(),
            'clock_in'    => '08:00:00',
            'clock_out'   => '17:00:00',
        ]);

        $this->postJson('/api/attendance/clock-out')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Already clocked out today.']);
    }

    public function test_late_status_is_set_when_clock_in_after_nine(): void
    {
        $user     = $this->loginAs('employee');
        $employee = $this->createEmployeeForUser($user);

        // travel time to 09:30 to simulate late clock-in
        $this->travelTo(now()->setTimeFromTimeString('09:30:00'));

        $this->postJson('/api/attendance/clock-in')
            ->assertStatus(201)
            ->assertJsonFragment(['status' => 'late']);

        $this->travelBack();
    }

    public function test_employee_can_view_own_attendance(): void
    {
        $user     = $this->loginAs('employee');
        $employee = $this->createEmployeeForUser($user);

        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2024-01-01']);
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2024-01-02']);
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2024-01-03']);

        $this->getJson('/api/attendance')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_employee_cannot_view_other_attendance_records(): void
    {
        $this->loginAs('employee');
        $otherEmployee = Employee::factory()->create();
        $attendance    = Attendance::factory()->create(['employee_id' => $otherEmployee->id]);

        $this->getJson("/api/attendance/{$attendance->id}")
            ->assertStatus(403);
    }

    public function test_admin_can_view_all_attendance(): void
    {
        $this->loginAs('admin');
        Attendance::factory(5)->create();

        $this->getJson('/api/attendance')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_admin_can_filter_attendance_by_date(): void
    {
        $this->loginAs('admin');

        Attendance::factory()->create(['date' => '2025-01-01']);
        Attendance::factory()->create(['date' => '2025-02-01']);

        $this->getJson('/api/attendance?date=2025-01-01')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_update_attendance(): void
    {
        $this->loginAs('admin');
        $attendance = Attendance::factory()->create();

        $this->putJson("/api/attendance/{$attendance->id}", [
            'status'  => 'half_day',
            'remarks' => 'Left early due to illness.',
        ])->assertOk()
            ->assertJsonFragment(['status' => 'half_day']);
    }

    public function test_employee_cannot_update_attendance(): void
    {
        $user       = $this->loginAs('employee');
        $employee   = $this->createEmployeeForUser($user);
        $attendance = Attendance::factory()->create(['employee_id' => $employee->id]);

        $this->putJson("/api/attendance/{$attendance->id}", [
            'status' => 'half_day',
        ])->assertStatus(403);
    }
}
