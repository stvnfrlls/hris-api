<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollPeriodResource;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollPeriodController extends Controller
{
    public function index()
    {
        $periods = PayrollPeriod::latest()->paginate(15);
        return PayrollPeriodResource::collection($periods);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        $data = $request->validate([
            'month'       => 'required|integer|between:1,12',
            'year'        => 'required|integer|min:2020',
            'period_type' => [
                'required',
                'in:first_half,second_half',
                Rule::unique('payroll_periods')
                    ->where('month', $request->month)
                    ->where('year', $request->year),
            ],
        ]);

        // auto set start and end dates based on period type
        $data['start_date'] = $data['period_type'] === 'first_half'
            ? "{$data['year']}-{$data['month']}-01"
            : "{$data['year']}-{$data['month']}-16";

        $data['end_date'] = $data['period_type'] === 'first_half'
            ? "{$data['year']}-{$data['month']}-15"
            : date('Y-m-t', strtotime("{$data['year']}-{$data['month']}-01"));

        $period = PayrollPeriod::create($data);

        return new PayrollPeriodResource($period);
    }

    public function show(PayrollPeriod $payrollPeriod)
    {
        return new PayrollPeriodResource($payrollPeriod);
    }
}
