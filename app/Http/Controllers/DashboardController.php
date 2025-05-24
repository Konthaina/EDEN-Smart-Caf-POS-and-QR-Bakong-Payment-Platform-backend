<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\User;
use App\Models\MenuItem;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function overview()
    {
        $today = now()->toDateString();

        $salesToday = Order::whereDate('created_at', $today)
            ->where('status', 'completed')
            ->sum('total_amount');

        $pendingOrders = Order::where('status', 'pending')->count();
        $menuItems = MenuItem::count();
        $customers = User::where('role_id', 4)->count();

        $dailySales = Order::selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->where('status', 'completed')
            ->whereDate('created_at', '>=', now()->subDays(6))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $labels = [];
        $values = [];

        $dates = collect(range(0, 6))->map(fn($i) => now()->subDays(6 - $i)->toDateString());

        foreach ($dates as $date) {
            $labels[] = Carbon::parse($date)->format('M d');
            $match = $dailySales->firstWhere('date', $date);
            $values[] = $match ? $match->total : 0;
        }

        return response()->json([
            'stats' => [
                'salesToday' => number_format($salesToday, 2),
                'pendingOrders' => $pendingOrders,
                'menuItems' => $menuItems,
                'customers' => $customers,
            ],
            'chart' => [
                'labels' => $labels,
                'values' => $values,
            ]
        ]);
    }
}
