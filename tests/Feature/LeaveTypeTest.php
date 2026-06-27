<?php

namespace Tests\Feature;

use App\Models\LeaveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthHelper;

class LeaveTypeTest extends TestCase
{
    use RefreshDatabase, AuthHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    private function leaveTypePayload(array $overrides = []): array
    {
        return array_merge([
            'name'         => 'Vacation Leave',
            'code'         => 'VL',
            'days_allowed' => 15,
        ], $overrides);
    }

    public function test_authenticated_user_can_list_leave_types(): void
    {
        $this->loginAs('employee');
        LeaveType::factory(3)->create();

        $this->getJson('/api/leave-types')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_admin_can_create_leave_type(): void
    {
        $this->loginAs('admin');

        $this->postJson('/api/leave-types', $this->leaveTypePayload())
            ->assertStatus(201)
            ->assertJsonFragment(['name' => 'Vacation Leave', 'code' => 'VL']);

        $this->assertDatabaseHas('leave_types', ['code' => 'VL']);
    }

    public function test_hr_cannot_create_leave_type(): void
    {
        $this->loginAs('hr');

        $this->postJson('/api/leave-types', $this->leaveTypePayload())
            ->assertStatus(403);
    }

    public function test_employee_cannot_create_leave_type(): void
    {
        $this->loginAs('employee');

        $this->postJson('/api/leave-types', $this->leaveTypePayload())
            ->assertStatus(403);
    }

    public function test_admin_can_update_leave_type(): void
    {
        $this->loginAs('admin');
        $leaveType = LeaveType::factory()->create();

        $this->putJson("/api/leave-types/{$leaveType->id}", [
            'days_allowed' => 20,
        ])->assertOk()
            ->assertJsonFragment(['days_allowed' => 20]);
    }

    public function test_admin_can_delete_leave_type(): void
    {
        $this->loginAs('admin');
        $leaveType = LeaveType::factory()->create();

        $this->deleteJson("/api/leave-types/{$leaveType->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Leave type deleted.']);

        $this->assertDatabaseMissing('leave_types', ['id' => $leaveType->id]);
    }

    public function test_duplicate_leave_type_code_is_rejected(): void
    {
        $this->loginAs('admin');
        LeaveType::factory()->create(['code' => 'VL']);

        $this->postJson('/api/leave-types', $this->leaveTypePayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_create_leave_type_validates_required_fields(): void
    {
        $this->loginAs('admin');

        $this->postJson('/api/leave-types', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code', 'days_allowed']);
    }
}
