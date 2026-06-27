<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveBalanceResource;
use App\Http\Resources\LeaveRequestResource;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $user  = $request->user();
        $query = LeaveRequest::with('employee.user', 'leaveType', 'reviewer')->latest();

        if ($user->hasRole('employee')) {
            $employee = Employee::where('user_id', $user->id)->firstOrFail();
            $query->where('employee_id', $employee->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('employee_id') && !$user->hasRole('employee')) {
            $query->where('employee_id', $request->employee_id);
        }

        return LeaveRequestResource::collection($query->paginate(15));
    }

    public function store(Request $request)
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $data = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date'    => 'required|date|after_or_equal:today',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'reason'        => 'nullable|string|max:500',
        ]);

        $leaveType     = LeaveType::findOrFail($data['leave_type_id']);
        $daysRequested = $this->countBusinessDays($data['start_date'], $data['end_date']);
        $year          = Carbon::parse($data['start_date'])->year;

        // get or initialize balance
        $balance = LeaveBalance::firstOrCreate(
            [
                'employee_id'   => $employee->id,
                'leave_type_id' => $leaveType->id,
                'year'          => $year,
            ],
            [
                'total_days' => $leaveType->days_allowed,
                'used_days'  => 0,
            ]
        );

        $balance->refresh();

        $remainingDays = $balance->total_days - $balance->used_days;

        if ($remainingDays < $daysRequested) {
            return response()->json([
                'message' => "Insufficient leave balance. You have {$balance->remaining_days} day(s) remaining.",
            ], 422);
        }

        // check for overlapping requests
        $overlap = LeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($data) {
                $q->whereBetween('start_date', [$data['start_date'], $data['end_date']])
                    ->orWhereBetween('end_date', [$data['start_date'], $data['end_date']]);
            })->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'You already have a leave request overlapping these dates.',
            ], 422);
        }

        $leaveRequest = LeaveRequest::create([
            ...$data,
            'employee_id'    => $employee->id,
            'days_requested' => $daysRequested,
            'status'         => 'pending',
        ]);

        return new LeaveRequestResource($leaveRequest->load('employee.user', 'leaveType'));
    }

    public function show(Request $request, LeaveRequest $leaveRequest)
    {
        $user = $request->user();

        if ($user->hasRole('employee')) {
            $employee = Employee::where('user_id', $user->id)->firstOrFail();

            if ($leaveRequest->employee_id !== $employee->id) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        return new LeaveRequestResource($leaveRequest->load('employee.user', 'leaveType', 'reviewer'));
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        $this->ensureReviewer($request);

        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        $year    = $leaveRequest->start_date->year;
        $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
            ->where('leave_type_id', $leaveRequest->leave_type_id)
            ->where('year', $year)
            ->firstOrFail();

        $balance->increment('used_days', $leaveRequest->days_requested);

        $leaveRequest->update([
            'status'      => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'remarks'     => $request->input('remarks'),
        ]);

        return new LeaveRequestResource($leaveRequest->load('employee.user', 'leaveType', 'reviewer'));
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $this->ensureReviewer($request);

        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
        }

        $leaveRequest->update([
            'status'      => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'remarks'     => $request->validate(['remarks' => 'required|string|max:500'])['remarks'],
        ]);

        return new LeaveRequestResource($leaveRequest->load('employee.user', 'leaveType', 'reviewer'));
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest)
    {
        $user     = $request->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        if ($leaveRequest->employee_id !== $employee->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (!in_array($leaveRequest->status, ['pending', 'approved'])) {
            return response()->json(['message' => 'This request can no longer be cancelled.'], 422);
        }

        // restore balance if cancelling an approved leave
        if ($leaveRequest->status === 'approved') {
            $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)
                ->where('year', $leaveRequest->start_date->year)
                ->firstOrFail();

            $balance->decrement('used_days', $leaveRequest->days_requested);
        }

        $leaveRequest->update(['status' => 'cancelled']);

        return new LeaveRequestResource($leaveRequest->load('employee.user', 'leaveType'));
    }

    public function balance(Request $request)
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();
        $year     = $request->input('year', now()->year);

        $balances = LeaveBalance::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->get();

        return LeaveBalanceResource::collection($balances);
    }

    private function ensureReviewer(Request $request): void
    {
        abort_unless(
            $request->user()->hasRole(['admin', 'hr']),
            403,
            'Only Admin or HR can review leave requests.'
        );
    }

    private function countBusinessDays(string $start, string $end): int
    {
        $start   = Carbon::parse($start);
        $end     = Carbon::parse($end);
        $days    = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            if ($current->isWeekday()) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }
}
