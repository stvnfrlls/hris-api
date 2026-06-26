<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    use AuthorizesRequests;
    
    // GET /api/attendance
    // admin/hr: all records; employee: own records only
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Attendance::with('employee.user')->latest('date');

        if ($user->hasRole('employee')) {
            $employee = Employee::where('user_id', $user->id)->firstOrFail();
            $query->where('employee_id', $employee->id);
        }

        // optional filters
        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->filled('employee_id') && !$user->hasRole('employee')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return AttendanceResource::collection($query->paginate(20));
    }

    // POST /api/attendance/clock-in
    public function clockIn(Request $request)
    {
        $user     = $request->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();
        $today    = Carbon::today();

        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        if ($attendance?->clock_in) {
            return response()->json([
                'message' => 'Already clocked in today.',
            ], 422);
        }

        $clockIn = Carbon::now();

        $attendance = Attendance::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $today],
            [
                'clock_in' => $clockIn->toTimeString(),
                'status'   => $clockIn->gt(Carbon::today()->setTimeFromTimeString('09:00:00'))
                    ? 'late'
                    : 'present',
            ]
        );

        return new AttendanceResource($attendance->load('employee.user'));
    }

    // POST /api/attendance/clock-out
    public function clockOut(Request $request)
    {
        $user     = $request->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();
        $today    = Carbon::today();

        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->firstOrFail();

        if (!$attendance->clock_in) {
            return response()->json([
                'message' => 'You have not clocked in yet.',
            ], 422);
        }

        if ($attendance->clock_out) {
            return response()->json([
                'message' => 'Already clocked out today.',
            ], 422);
        }

        $attendance->update([
            'clock_out' => Carbon::now()->toTimeString(),
        ]);

        return new AttendanceResource($attendance->load('employee.user'));
    }

    // GET /api/attendance/{attendance}
    public function show(Request $request, Attendance $attendance)
    {
        $this->authorize('view', $attendance);

        $user = $request->user();

        if ($user->hasRole('employee')) {
            $employee = Employee::where('user_id', $user->id)->firstOrFail();

            if ($attendance->employee_id !== $employee->id) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        return new AttendanceResource($attendance->load('employee.user'));
    }

    // PUT /api/attendance/{attendance} — admin/hr only
    public function update(Request $request, Attendance $attendance)
    {
        $this->authorize('manage attendance', $request->user());

        $data = $request->validate([
            'clock_in'  => 'sometimes|date_format:H:i',
            'clock_out' => 'sometimes|date_format:H:i|after:clock_in',
            'status'    => 'sometimes|in:present,absent,late,half_day',
            'remarks'   => 'sometimes|string|max:500',
        ]);

        $attendance->update($data);

        return new AttendanceResource($attendance->load('employee.user'));
    }
}
