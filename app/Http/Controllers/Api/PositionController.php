<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PositionResource;
use App\Models\Position;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    use AuthorizesRequests;
    
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $positions = Position::with('department')
            ->when(
                $request->department_id,
                fn($q) =>
                $q->where('department_id', $request->department_id)
            )
            ->when(
                $request->employment_type,
                fn($q) =>
                $q->where('employment_type', $request->employment_type)
            )
            ->latest()
            ->paginate(15);

        return PositionResource::collection($positions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Position::class);

        $data = $request->validate([
            'department_id'   => 'required|exists:departments,id',
            'name'            => 'required|string|max:100',
            'employment_type' => 'required|in:full_time,part_time,contractual',
            'is_active'       => 'sometimes|boolean',
        ]);

        $position = Position::create($data);

        return new PositionResource($position->load('department'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Position $position)
    {
        return new PositionResource($position->load('department'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Position $position)
    {
        $this->authorize('update', Position::class);

        $data = $request->validate([
            'department_id'   => 'sometimes|exists:departments,id',
            'name'            => 'sometimes|string|max:100',
            'employment_type' => 'sometimes|in:full_time,part_time,contractual',
            'is_active'       => 'sometimes|boolean',
        ]);

        $position->update($data);

        return new PositionResource($position->load('department'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Position $position)
    {
        $this->authorize('delete', Position::class);

        if ($position->employees()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a position with existing employees.',
            ], 422);
        }

        $position->delete();

        return response()->json(['message' => 'Position deleted.']);
    }
}
