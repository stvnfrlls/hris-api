<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollDeduction;
use App\Models\PayrollPeriod;

class PayrollService
{
    // working days per half month
    const WORKING_DAYS_PER_PERIOD = 13;

    public function generate(PayrollPeriod $period): array
    {
        $employees = Employee::with('salaryDetail')->where('status', 'active')->get();
        $payrolls  = [];

        foreach ($employees as $employee) {
            $salaryDetail = $employee->salaryDetail;

            if (!$salaryDetail) {
                continue;
            }

            $payroll    = $this->computeForEmployee($employee, $period);
            $payrolls[] = $payroll;
        }

        $period->update(['status' => 'processed']);

        return $payrolls;
    }

    public function computeForEmployee(Employee $employee, PayrollPeriod $period): Payroll
    {
        $salaryDetail = $employee->salaryDetail ?? $employee->load('salaryDetail')->salaryDetail;

        if (!$salaryDetail) {
            throw new \RuntimeException("No salary detail found for employee {$employee->id}");
        }

        $salary      = $employee->salaryDetail->basic_salary;
        $dailyRate   = $salary / 26; // 26 working days per month
        $minuteRate  = $dailyRate / 480; // 8 hours = 480 minutes

        // pull attendance for this period
        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$period->start_date, $period->end_date])
            ->get();

        $daysWorked  = $attendances->whereIn('status', ['present', 'late'])->count();
        $daysAbsent  = self::WORKING_DAYS_PER_PERIOD - $daysWorked;
        $lateMinutes = $this->computeLateMinutes($attendances);

        // gross pay = salary for days worked
        $grossPay       = $dailyRate * $daysWorked;
        $lateDeduction  = $minuteRate * $lateMinutes;

        // government mandated deductions (semi-monthly)
        $sss        = $this->computeSSS($salary);
        $philhealth = $this->computePhilHealth($salary);
        $pagibig    = $this->computePagIbig($salary);

        $totalDeductions = $lateDeduction + $sss + $philhealth + $pagibig;
        $netPay          = $grossPay - $totalDeductions;

        // upsert payroll record
        $payroll = Payroll::updateOrCreate(
            [
                'employee_id'       => $employee->id,
                'payroll_period_id' => $period->id,
            ],
            [
                'basic_salary'     => $salary,
                'days_worked'      => $daysWorked,
                'days_absent'      => max(0, $daysAbsent),
                'late_minutes'     => $lateMinutes,
                'gross_pay'        => round($grossPay, 2),
                'total_deductions' => round($totalDeductions, 2),
                'net_pay'          => round($netPay, 2),
                'status'           => 'draft',
            ]
        );

        // clear and re-insert deductions
        $payroll->deductions()->delete();

        $deductions = [
            ['name' => 'Late Deduction', 'type' => 'custom',     'amount' => round($lateDeduction, 2)],
            ['name' => 'SSS',            'type' => 'sss',        'amount' => round($sss, 2)],
            ['name' => 'PhilHealth',     'type' => 'philhealth', 'amount' => round($philhealth, 2)],
            ['name' => 'Pag-IBIG',       'type' => 'pagibig',    'amount' => round($pagibig, 2)],
        ];

        foreach ($deductions as $deduction) {
            if ($deduction['amount'] > 0) {
                PayrollDeduction::create([...$deduction, 'payroll_id' => $payroll->id]);
            }
        }

        return $payroll->load('deductions');
    }

    // SSS contribution table (semi-monthly employee share)
    private function computeSSS(float $monthlySalary): float
    {
        $brackets = [
            [4250,  135.00],
            [4750,  157.50],
            [5250,  180.00],
            [5750,  202.50],
            [6250,  225.00],
            [6750,  247.50],
            [7250,  270.00],
            [7750,  292.50],
            [8250,  315.00],
            [8750,  337.50],
            [9250,  360.00],
            [9750,  382.50],
            [10250, 405.00],
            [10750, 427.50],
            [11250, 450.00],
            [11750, 472.50],
            [12250, 495.00],
            [12750, 517.50],
            [13250, 540.00],
            [13750, 562.50],
            [14250, 585.00],
            [14750, 607.50],
            [15250, 630.00],
            [15750, 652.50],
            [16250, 675.00],
            [16750, 697.50],
            [17250, 720.00],
            [17750, 742.50],
            [18250, 765.00],
            [18750, 787.50],
            [19250, 810.00],
            [19750, 832.50],
            [20250, 855.00],
            [PHP_INT_MAX, 900.00],
        ];

        foreach ($brackets as [$cap, $contribution]) {
            if ($monthlySalary <= $cap) {
                return $contribution / 2; // semi-monthly share
            }
        }

        return 900.00 / 2;
    }

    // PhilHealth — 5% of monthly salary, split equally (employee: 2.5%)
    private function computePhilHealth(float $monthlySalary): float
    {
        $monthlyContribution = max(500, min($monthlySalary * 0.05, 5000));
        return ($monthlyContribution / 2) / 2; // employee share, semi-monthly
    }

    // Pag-IBIG — fixed PHP 100/month employee share
    private function computePagIbig(float $monthlySalary): float
    {
        return 100 / 2; // semi-monthly
    }

    private function computeLateMinutes($attendances): int
    {
        $lateMinutes = 0;
        $cutoff      = '09:00:00';

        foreach ($attendances as $attendance) {
            if ($attendance->status === 'late' && $attendance->clock_in) {
                $clockIn     = strtotime($attendance->getRawOriginal('clock_in'));
                $cutoffTime  = strtotime($attendance->date->format('Y-m-d') . ' ' . $cutoff);
                $diff        = ($clockIn - $cutoffTime) / 60;
                $lateMinutes += max(0, (int) $diff);
            }
        }

        return $lateMinutes;
    }
}
