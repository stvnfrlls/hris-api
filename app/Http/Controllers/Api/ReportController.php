<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    // GET /api/reports/attendance-summary
    public function attendanceSummary(Request $request)
    {
        $this->ensureAdminOrHr($request);

        $month = $request->input('month', now()->month);
        $year  = $request->input('year', now()->year);

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate   = Carbon::create($year, $month, 1)->endOfMonth();

        $summary = Employee::with('user')
            ->withCount([
                'attendances as present_count' => fn($q) =>
                $q->whereBetween('date', [$startDate, $endDate])->where('status', 'present'),
                'attendances as late_count' => fn($q) =>
                $q->whereBetween('date', [$startDate, $endDate])->where('status', 'late'),
                'attendances as absent_count' => fn($q) =>
                $q->whereBetween('date', [$startDate, $endDate])->where('status', 'absent'),
                'attendances as half_day_count' => fn($q) =>
                $q->whereBetween('date', [$startDate, $endDate])->where('status', 'half_day'),
            ])
            ->get()
            ->map(fn($employee) => [
                'employee_id'    => $employee->id,
                'employee_code'  => $employee->employee_code,
                'name'           => $employee->user->name,
                'present_count'  => $employee->present_count,
                'late_count'     => $employee->late_count,
                'absent_count'   => $employee->absent_count,
                'half_day_count' => $employee->half_day_count,
                'total_days'     => $employee->present_count + $employee->late_count
                    + $employee->absent_count + $employee->half_day_count,
            ]);

        return response()->json([
            'period' => [
                'month'      => (int) $month,
                'year'       => (int) $year,
                'start_date' => $startDate->toDateString(),
                'end_date'   => $endDate->toDateString(),
            ],
            'data'   => $summary,
            'totals' => [
                'total_present'  => $summary->sum('present_count'),
                'total_late'     => $summary->sum('late_count'),
                'total_absent'   => $summary->sum('absent_count'),
                'total_half_day' => $summary->sum('half_day_count'),
            ],
        ]);
    }

    // GET /api/reports/tardiness
    public function tardiness(Request $request)
    {
        $this->ensureAdminOrHr($request);

        $month = $request->input('month', now()->month);
        $year  = $request->input('year', now()->year);

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate   = Carbon::create($year, $month, 1)->endOfMonth();

        $tardiness = Employee::with('user')
            ->withCount([
                'attendances as late_count' => fn($q) =>
                $q->whereBetween('date', [$startDate, $endDate])->where('status', 'late')
            ])
            ->get()
            ->filter(fn($employee) => $employee->late_count > 0)
            ->sortByDesc('late_count')
            ->values()
            ->map(fn($employee) => [
                'employee_id'   => $employee->id,
                'employee_code' => $employee->employee_code,
                'name'          => $employee->user->name,
                'late_count'    => $employee->late_count,
            ]);

        return response()->json([
            'period' => ['month' => (int) $month, 'year' => (int) $year],
            'data'   => $tardiness,
        ]);
    }

    // GET /api/reports/payroll-summary
    public function payrollSummary(Request $request)
    {
        $this->ensureAdminOrHr($request);

        $periodId = $request->input('payroll_period_id');

        $query = Payroll::with('employee.department', 'period');

        if ($periodId) {
            $query->where('payroll_period_id', $periodId);
        } else {
            // default to most recent period
            $query->whereHas(
                'period',
                fn($q) =>
                $q->where('year', now()->year)->where('month', now()->month)
            );
        }

        $payrolls = $query->get();

        // group by department
        $byDepartment = $payrolls->groupBy(fn($p) => $p->employee->department->name ?? 'Unassigned')
            ->map(fn($group, $deptName) => [
                'department'        => $deptName,
                'employee_count'    => $group->count(),
                'total_gross_pay'   => round($group->sum('gross_pay'), 2),
                'total_deductions'  => round($group->sum('total_deductions'), 2),
                'total_net_pay'     => round($group->sum('net_pay'), 2),
            ])
            ->values();

        return response()->json([
            'data'   => $byDepartment,
            'totals' => [
                'employee_count'   => $payrolls->count(),
                'total_gross_pay'  => round($payrolls->sum('gross_pay'), 2),
                'total_deductions' => round($payrolls->sum('total_deductions'), 2),
                'total_net_pay'    => round($payrolls->sum('net_pay'), 2),
            ],
        ]);
    }

    // GET /api/reports/headcount
    public function headcount(Request $request)
    {
        $this->ensureAdminOrHr($request);

        $byDepartment = Department::withCount('employees')
            ->get()
            ->map(fn($dept) => [
                'department' => $dept->name,
                'code'       => $dept->code,
                'count'      => $dept->employees_count,
            ]);

        $byEmploymentType = Employee::join('positions', 'employees.position_id', '=', 'positions.id')
            ->select('positions.employment_type', DB::raw('count(*) as count'))
            ->groupBy('positions.employment_type')
            ->get();

        $byStatus = Employee::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        return response()->json([
            'by_department'      => $byDepartment,
            'by_employment_type' => $byEmploymentType,
            'by_status'          => $byStatus,
            'total_employees'    => Employee::count(),
        ]);
    }

    private function ensureAdminOrHr(Request $request): void
    {
        abort_unless($request->user()->hasRole(['admin', 'hr']), 403);
    }
}
