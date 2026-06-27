<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthHelper;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase, AuthHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    // helper — create employee linked to a user
    private function createEmployeeForUser(User $user): Employee
    {
        return Employee::factory()->create(['user_id' => $user->id]);
    }

    // helper — create a leave balance with enough days
    private function createBalance(Employee $employee, LeaveType $leaveType, int $totalDays = 15): LeaveBalance
    {
        return LeaveBalance::factory()->create([
            'employee_id'   => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year'          => now()->year,
            'total_days'    => $totalDays,
            'used_days'     => 0,
        ]);
    }

    private function leavePayload(int $leaveTypeId, array $overrides = []): array
    {
        return array_merge([
            'leave_type_id' => $leaveTypeId,
            'start_date'    => now()->addDays(2)->toDateString(),
            'end_date'      => now()->addDays(4)->toDateString(),
            'reason'        => 'Family vacation',
        ], $overrides);
    }

    public function test_employee_can_apply_for_leave(): void
    {
        $user      = $this->loginAs('employee');
        $employee  = $this->createEmployeeForUser($user);
        $leaveType = LeaveType::factory()->create(['days_allowed' => 15]);
        $this->createBalance($employee, $leaveType);

        $this->postJson('/api/leave-requests', $this->leavePayload($leaveType->id))
            ->assertStatus(201)
            ->assertJsonFragment(['status' => 'pending']);

        $this->assertDatabaseHas('leave_requests', [
            'employee_id'   => $employee->id,
            'leave_type_id' => $leaveType->id,
            'status'        => 'pending',
        ]);
    }

    public function test_employee_cannot_apply_with_insufficient_balance(): void
    {
        $user      = $this->loginAs('employee');
        $employee  = $this->createEmployeeForUser($user);
        $leaveType = LeaveType::factory()->create(['days_allowed' => 15]);
        $this->createBalance($employee, $leaveType, totalDays: 1);

        $this->postJson('/api/leave-requests', $this->leavePayload($leaveType->id))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Insufficient leave balance. You have 1 day(s) remaining.']);
    }

    public function test_employee_cannot_apply_with_overlapping_dates(): void
    {
        $user      = $this->loginAs('employee');
        $employee  = $this->createEmployeeForUser($user);
        $leaveType = LeaveType::factory()->create(['days_allowed' => 15]);
        $this->createBalance($employee, $leaveType);

        // first request
        $this->postJson('/api/leave-requests', $this->leavePayload($leaveType->id))
            ->assertStatus(201);

        // overlapping second request
        $this->postJson('/api/leave-requests', $this->leavePayload($leaveType->id))
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'You already have a leave request overlapping these dates.',
            ]);
    }

    public function test_employee_can_view_own_leave_requests(): void
    {
        $user     = $this->loginAs('employee');
        $employee = $this->createEmployeeForUser($user);
        LeaveRequest::factory(3)->create(['employee_id' => $employee->id]);

        $this->getJson('/api/leave-requests')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_employee_cannot_view_other_leave_requests(): void
    {
        $this->loginAs('employee');
        $otherRequest = LeaveRequest::factory()->create();

        $this->getJson("/api/leave-requests/{$otherRequest->id}")
            ->assertStatus(404);
    }

    public function test_admin_can_view_all_leave_requests(): void
    {
        $this->loginAs('admin');
        LeaveRequest::factory(5)->create();

        $this->getJson('/api/leave-requests')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_admin_can_approve_leave_request(): void
    {
        $this->loginAs('admin');
        $leaveType = LeaveType::factory()->create(['days_allowed' => 15]);
        $request   = LeaveRequest::factory()->create([
            'leave_type_id'  => $leaveType->id,
            'status'         => 'pending',
            'days_requested' => 3,
        ]);

        LeaveBalance::factory()->create([
            'employee_id'   => $request->employee_id,
            'leave_type_id' => $leaveType->id,
            'year'          => now()->year,
            'total_days'    => 15,
            'used_days'     => 0,
        ]);

        $this->postJson("/api/leave-requests/{$request->id}/approve", [
            'remarks' => 'Approved.',
        ])->assertOk()
            ->assertJsonFragment(['status' => 'approved']);

        $this->assertDatabaseHas('leave_requests', [
            'id'     => $request->id,
            'status' => 'approved',
        ]);
    }

    public function test_hr_can_approve_leave_request(): void
    {
        $this->loginAs('hr');
        $leaveType = LeaveType::factory()->create(['days_allowed' => 15]);
        $request   = LeaveRequest::factory()->create([
            'leave_type_id'  => $leaveType->id,
            'status'         => 'pending',
            'days_requested' => 3,
        ]);

        LeaveBalance::factory()->create([
            'employee_id'   => $request->employee_id,
            'leave_type_id' => $leaveType->id,
            'year'          => now()->year,
            'total_days'    => 15,
            'used_days'     => 0,
        ]);

        $this->postJson("/api/leave-requests/{$request->id}/approve", [
            'remarks' => 'Approved.',
        ])->assertOk()
            ->assertJsonFragment(['status' => 'approved']);
    }

    public function test_employee_cannot_approve_leave_request(): void
    {
        $this->loginAs('employee');
        $request = LeaveRequest::factory()->create(['status' => 'pending']);

        $this->postJson("/api/leave-requests/{$request->id}/approve")
            ->assertStatus(403);
    }

    public function test_admin_can_reject_leave_request(): void
    {
        $this->loginAs('admin');
        $request = LeaveRequest::factory()->create(['status' => 'pending']);

        $this->postJson("/api/leave-requests/{$request->id}/reject", [
            'remarks' => 'Insufficient staffing.',
        ])->assertOk()
            ->assertJsonFragment(['status' => 'rejected']);
    }

    public function test_reject_requires_remarks(): void
    {
        $this->loginAs('admin');
        $request = LeaveRequest::factory()->create(['status' => 'pending']);

        $this->postJson("/api/leave-requests/{$request->id}/reject", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['remarks']);
    }

    public function test_cannot_approve_already_approved_request(): void
    {
        $this->loginAs('admin');
        $request = LeaveRequest::factory()->create(['status' => 'approved']);

        $this->postJson("/api/leave-requests/{$request->id}/approve")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Only pending requests can be approved.']);
    }

    public function test_cannot_reject_already_rejected_request(): void
    {
        $this->loginAs('admin');
        $request = LeaveRequest::factory()->create(['status' => 'rejected']);

        $this->postJson("/api/leave-requests/{$request->id}/reject", [
            'remarks' => 'Already rejected.',
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'Only pending requests can be rejected.']);
    }

    public function test_employee_can_cancel_own_pending_request(): void
    {
        $user     = $this->loginAs('employee');
        $employee = $this->createEmployeeForUser($user);
        $request  = LeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'status'      => 'pending',
        ]);

        $this->postJson("/api/leave-requests/{$request->id}/cancel")
            ->assertOk()
            ->assertJsonFragment(['status' => 'cancelled']);
    }

    public function test_employee_can_cancel_own_approved_request_and_balance_is_restored(): void
    {
        $user      = $this->loginAs('employee');
        $employee  = $this->createEmployeeForUser($user);
        $leaveType = LeaveType::factory()->create();

        $request = LeaveRequest::factory()->create([
            'employee_id'    => $employee->id,
            'leave_type_id'  => $leaveType->id,
            'status'         => 'approved',
            'days_requested' => 3,
        ]);

        $balance = LeaveBalance::factory()->create([
            'employee_id'   => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year'          => now()->year,
            'total_days'    => 15,
            'used_days'     => 3,
        ]);

        $this->postJson("/api/leave-requests/{$request->id}/cancel")
            ->assertOk()
            ->assertJsonFragment(['status' => 'cancelled']);

        // balance should be restored
        $this->assertDatabaseHas('leave_balances', [
            'id'        => $balance->id,
            'used_days' => 0,
        ]);
    }

    public function test_employee_cannot_cancel_other_employee_request(): void
    {
        $user     = $this->loginAs('employee');
        $this->createEmployeeForUser($user);
        $otherRequest = LeaveRequest::factory()->create(['status' => 'pending']);

        $this->postJson("/api/leave-requests/{$otherRequest->id}/cancel")
            ->assertStatus(403);
    }

    public function test_employee_can_view_own_leave_balance(): void
    {
        $user      = $this->loginAs('employee');
        $employee  = $this->createEmployeeForUser($user);
        $leaveType = LeaveType::factory()->create();

        LeaveBalance::factory()->create([
            'employee_id'   => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year'          => now()->year,
            'total_days'    => 15,
            'used_days'     => 5,
        ]);

        $this->getJson('/api/leave-requests/balance')
            ->assertOk()
            ->assertJsonFragment([
                'total_days'     => 15,
                'used_days'      => 5,
                'remaining_days' => 10,
            ]);
    }

    public function test_apply_for_leave_validates_required_fields(): void
    {
        $user = $this->loginAs('employee');
        $this->createEmployeeForUser($user);

        $this->postJson('/api/leave-requests', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['leave_type_id', 'start_date', 'end_date']);
    }

    public function test_cannot_apply_for_leave_with_past_start_date(): void
    {
        $user      = $this->loginAs('employee');
        $employee  = $this->createEmployeeForUser($user);
        $leaveType = LeaveType::factory()->create(['days_allowed' => 15]);
        $this->createBalance($employee, $leaveType);

        $this->postJson('/api/leave-requests', $this->leavePayload($leaveType->id, [
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date'   => now()->subDay()->toDateString(),
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);
    }

    public function test_can_filter_leave_requests_by_status(): void
    {
        $this->loginAs('admin');
        LeaveRequest::factory(2)->create(['status' => 'pending']);
        LeaveRequest::factory(3)->create(['status' => 'approved']);

        $this->getJson('/api/leave-requests?status=pending')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
