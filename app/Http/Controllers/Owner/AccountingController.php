<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\AccountingEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountingController extends Controller
{
    public function index(Request $request)
    {
        $mallId = Auth::user()->mall_id;
        if (!$mallId) {
            return response()->json(['message' => 'No mall associated with this user'], 403);
        }

        $query = AccountingEntry::where('mall_id', $mallId);

        if ($request->filled('from')) {
            $query->whereDate('entry_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('entry_date', '<=', $request->to);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $entries = $query->orderBy('entry_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 50);

        return response()->json($entries);
    }

    public function store(Request $request)
    {
        $mallId = Auth::user()->mall_id;
        if (!$mallId) {
            return response()->json(['message' => 'No mall associated with this user'], 403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric',
            'type' => 'required|in:income,expense',
            'description' => 'required|string|max:255',
            'entry_date' => 'required|date',
        ]);

        $entry = AccountingEntry::create([
            'mall_id' => $mallId,
            'amount' => $validated['amount'],
            'type' => $validated['type'],
            'description' => $validated['description'],
            'entry_date' => $validated['entry_date'],
        ]);

        return response()->json($entry, 201);
    }

    public function destroy($id)
    {
        $mallId = Auth::user()->mall_id;
        $entry = AccountingEntry::where('mall_id', $mallId)->findOrFail($id);
        $entry->delete();

        return response()->json(['message' => 'Entry deleted successfully']);
    }

    public function stats()
    {
        $mallId = Auth::user()->mall_id;
        if (!$mallId) {
            return response()->json(['message' => 'No mall associated with this user'], 403);
        }

        $income = AccountingEntry::where('mall_id', $mallId)->where('type', 'income')->sum('amount');
        $expenses = AccountingEntry::where('mall_id', $mallId)->where('type', 'expense')->sum('amount');

        return response()->json([
            'total_income' => $income,
            'total_expenses' => $expenses,
            'balance' => $income - $expenses
        ]);
    }
}
