<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollResource;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(private PayrollService $payrollService) {}

    // GET /api/payroll — list payrolls (admin/hr: all, employee: own)
    public function index(Request $request)
    {
        $user  = $request->user();
        $query = Payroll::with('employee.user', 'period', 'deductions')->latest();

        if ($user->hasRole('employee')) {
            $employee = Employee::where('user_id', $user->id)->firstOrFail();
            $query->where('employee_id', $employee->id);
        }

        if ($request->filled('period_id')) {
            $query->where('payroll_period_id', $request->period_id);
        }

        if ($request->filled('year')) {
            $query->whereHas('period', fn($q) => $q->where('year', $request->year));
        }

        return PayrollResource::collection($query->paginate(20));
    }

    // POST /api/payroll/generate — generate payroll for a period
    public function generate(Request $request)
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        $data = $request->validate([
            'payroll_period_id' => 'required|exists:payroll_periods,id',
        ]);

        $period = PayrollPeriod::findOrFail($data['payroll_period_id']);

        if ($period->status === 'released') {
            return response()->json([
                'message' => 'Cannot regenerate a released payroll period.',
            ], 422);
        }

        $payrolls = $this->payrollService->generate($period);

        return response()->json([
            'message'  => "Payroll generated for {$period->start_date->format('M d')} - {$period->end_date->format('M d, Y')}.",
            'count'    => count($payrolls),
        ]);
    }

    // GET /api/payroll/{payroll}
    public function show(Request $request, Payroll $payroll)
    {
        $user = $request->user();

        if ($user->hasRole('employee')) {
            $employee = Employee::where('user_id', $user->id)->firstOrFail();

            if ($payroll->employee_id !== $employee->id) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        return new PayrollResource($payroll->load('employee.user', 'period', 'deductions'));
    }

    // POST /api/payroll/{payroll}/release
    public function release(Request $request, Payroll $payroll)
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        if ($payroll->status === 'released') {
            return response()->json(['message' => 'Payroll already released.'], 422);
        }

        $payroll->update(['status' => 'released']);

        return new PayrollResource($payroll->load('employee.user', 'period', 'deductions'));
    }
}
