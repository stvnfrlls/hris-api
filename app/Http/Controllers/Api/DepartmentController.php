<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    use AuthorizesRequests;
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $departments = Department::withCount('positions')
            ->latest()
            ->paginate(15);

        return DepartmentResource::collection($departments);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Department::class);

        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'code'        => 'required|string|max:20|unique:departments,code',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'sometimes|boolean',
        ]);

        $department = Department::create($data);

        return new DepartmentResource($department);
    }

    /**
     * Display the specified resource.
     */
    public function show(Department $department)
    {
        return new DepartmentResource(
            $department->load('positions')
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Department $department)
    {
        $this->authorize('update', Department::class);

        $data = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'code'        => "sometimes|string|max:20|unique:departments,code,{$department->id}",
            'description' => 'nullable|string|max:500',
            'is_active'   => 'sometimes|boolean',
        ]);

        $department->update($data);

        return new DepartmentResource($department);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Department $department)
    {
        $this->authorize('delete', Department::class);

        if ($department->employees()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a department with existing employees.',
            ], 422);
        }

        $department->delete();

        return response()->json(['message' => 'Department deleted.']);
    }
}
