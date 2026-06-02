<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Mall;
use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;

class AdminStatsController extends Controller
{
    public function index()
    {
        return response()->json([
            'malls_count' => Mall::count(),
            'users_count' => User::count(),
            'revenue' => Order::where('status', 'completed')->sum('total_amount'),
            'pending_malls' => Mall::where('status', 'pending')->count(),
        ]);
    }

    public function pendingMalls()
    {
        return response()->json(Mall::where('status', 'pending')->with('owner')->get());
    }
}
