<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalaryDetailResource;
use App\Models\Employee;
use App\Models\SalaryDetail;
use Illuminate\Http\Request;

class SalaryDetailController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureAdminOrHr($request);

        $salaries = SalaryDetail::with('employee.user')->paginate(15);
        return SalaryDetailResource::collection($salaries);
    }

    public function store(Request $request)
    {
        $this->ensureAdminOrHr($request);

        $data = $request->validate([
            'employee_id'  => 'required|exists:employees,id|unique:salary_details,employee_id',
            'basic_salary' => 'required|numeric|min:0',
        ]);

        $salary = SalaryDetail::create($data);

        return new SalaryDetailResource($salary->load('employee.user'));
    }

    public function show(Employee $employee)
    {
        $salary = SalaryDetail::where('employee_id', $employee->id)->firstOrFail();
        return new SalaryDetailResource($salary->load('employee.user'));
    }

    public function update(Request $request, Employee $employee)
    {
        $this->ensureAdminOrHr($request);

        $data = $request->validate([
            'basic_salary' => 'required|numeric|min:0',
        ]);

        $salary = SalaryDetail::where('employee_id', $employee->id)->firstOrFail();
        $salary->update($data);

        return new SalaryDetailResource($salary->load('employee.user'));
    }

    private function ensureAdminOrHr(Request $request): void
    {
        abort_unless($request->user()->hasRole(['admin', 'hr']), 403);
    }
}
