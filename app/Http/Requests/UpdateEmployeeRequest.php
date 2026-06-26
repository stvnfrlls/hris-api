<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('update employees');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employeeId = $this->route('employee')->id;

        return [
            'employee_code'   => "sometimes|string|unique:employees,employee_code,{$employeeId}",
            'department'      => 'sometimes|string|max:100',
            'position'        => 'sometimes|string|max:100',
            'employment_type' => 'sometimes|in:full_time,part_time,contractual',
            'hire_date'       => 'sometimes|date',
            'status'          => 'sometimes|in:active,inactive,terminated',
        ];
    }
}
