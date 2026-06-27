<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveTypeResource;
use App\Models\LeaveType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    use AuthorizesRequests;
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return LeaveTypeResource::collection(
            LeaveType::where('is_active', true)->get()
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', LeaveType::class);

        $data = $request->validate([
            'name'         => 'required|string|max:100',
            'code'         => 'required|string|max:20|unique:leave_types,code',
            'days_allowed' => 'required|integer|min:1',
            'is_active'    => 'sometimes|boolean',
        ]);

        $leaveType = LeaveType::create($data);

        return new LeaveTypeResource($leaveType);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LeaveType $leaveType)
    {
        $this->authorize('update', LeaveType::class);

        $data = $request->validate([
            'name'         => 'sometimes|string|max:100',
            'code'         => "sometimes|string|max:20|unique:leave_types,code,{$leaveType->id}",
            'days_allowed' => 'sometimes|integer|min:1',
            'is_active'    => 'sometimes|boolean',
        ]);

        $leaveType->update($data);

        return new LeaveTypeResource($leaveType);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LeaveType $leaveType)
    {
        $this->authorize('delete', LeaveType::class);

        $leaveType->delete();

        return response()->json(['message' => 'Leave type deleted.']);
    }
}
