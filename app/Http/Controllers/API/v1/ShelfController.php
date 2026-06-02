<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\MallBranch;
use App\Models\Shelf;
use App\Repositories\ShelfRepository;
use Illuminate\Http\Request;

class ShelfController extends Controller
{
    protected $shelfRepository;

    public function __construct(ShelfRepository $shelfRepository)
    {
        $this->shelfRepository = $shelfRepository;
    }

    public function index(Request $request)
    {
        if ($request->has('mall_id')) {
            return response()->json($this->shelfRepository->getByMall($request->mall_id));
        }
        if ($request->has('branch_id')) {
            return response()->json($this->shelfRepository->getByBranch($request->branch_id));
        }
        return response()->json(['message' => 'Missing mall_id or branch_id'], 400);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'mall_id'         => 'required_without:branch_id|nullable|exists:malls,id',
            'branch_id'       => 'required_without:mall_id|nullable|exists:mall_branches,id',
            'name'            => 'required|string|max:255',
            'section'         => 'nullable|string|max:255',
            'map_coordinates' => 'nullable|array',
        ]);

        // Security check: ensure user owns the mall or the mall of the branch
        if (isset($validated['mall_id'])) {
            $mall = \App\Models\Mall::findOrFail($validated['mall_id']);
            if ($mall->owner_id !== $request->user()->id && !$request->user()->hasRole('admin')) {
                return response()->json(['message' => 'Unauthorized Mall'], 403);
            }
        } else {
            $branch = MallBranch::findOrFail($validated['branch_id']);
            if ($branch->mall->owner_id !== $request->user()->id && !$request->user()->hasRole('admin')) {
                return response()->json(['message' => 'Unauthorized Branch'], 403);
            }
        }

        $shelf = $this->shelfRepository->create($validated);
        return response()->json($shelf, 201);
    }

    public function update(Request $request, $id)
    {
        $shelf = Shelf::findOrFail($id);
        
        $validated = $request->validate([
            'name'            => 'sometimes|required|string|max:255',
            'section'         => 'sometimes|nullable|string|max:255',
            'map_coordinates' => 'sometimes|nullable|array',
        ]);

        $shelf->update($validated);
        return response()->json($shelf);
    }

    public function destroy($id)
    {
        $shelf = Shelf::findOrFail($id);
        $shelf->delete();
        return response()->json(['message' => 'Shelf deleted']);
    }

    public function getMallShelves($mallId)
    {
        // Get shelves linked directly to mall OR linked to branches of this mall
        $shelves = Shelf::where('mall_id', $mallId)
            ->orWhereHas('branch', function($q) use ($mallId) {
                $q->where('mall_id', $mallId);
            })->get();

        return response()->json($shelves);
    }
}
