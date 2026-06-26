<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('create employees');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id'         => 'required|exists:users,id|unique:employees,user_id',
            'employee_code'   => 'required|string|unique:employees,employee_code',
            'department'      => 'required|string|max:100',
            'position'        => 'required|string|max:100',
            'employment_type' => 'required|in:full_time,part_time,contractual',
            'hire_date'       => 'required|date',
            'status'          => 'sometimes|in:active,inactive,terminated',
        ];
    }
}
