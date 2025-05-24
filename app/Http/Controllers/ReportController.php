<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\MenuItem;

class ReportController extends Controller
{
    // 📅 Summary for today
    public function summary()
    {
        $today = Carbon::today();

        $orders = Order::whereDate('created_at', $today)->get();
        return response()->json([
            'date' => $today->toDateString(),
            'total_revenue' => $orders->sum('total_amount'),
            'order_count' => $orders->count(),
        ]);
    }

    // 📈 Top 5 best-selling items (all time)
    public function topItems()
    {
        $topItems = MenuItem::select('menu_items.name', DB::raw('SUM(order_items.quantity) as total_orders'))
            ->join('order_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->groupBy('menu_items.name')
            ->orderByDesc('total_orders')
            ->limit(10)
            ->get();

        return response()->json($topItems);
    }

    // 📊 Weekly and monthly stats
    public function stats()
    {
        $weekly = Order::selectRaw('DATE(created_at) as date, SUM(total_amount) as revenue')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $monthly = Order::selectRaw('MONTH(created_at) as month, SUM(total_amount) as revenue')
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'weekly' => $weekly,
            'monthly' => $monthly
        ]);
    }

    // 📆 Monthly revenue for bar chart
    public function monthlyRevenue()
    {
        $revenues = DB::table('orders')
            ->selectRaw("DATE_FORMAT(created_at, '%b') as month, SUM(total_amount) as revenue")
            ->whereYear('created_at', now()->year)
            ->groupBy('month')
            ->orderByRaw("STR_TO_DATE(month, '%b')")
            ->get();

        $months = collect([
            'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
            'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
        ]);

        $data = $months->map(function ($month) use ($revenues) {
            $match = $revenues->firstWhere('month', $month);
            return [
                'month' => $month,
                'revenue' => $match ? (float) $match->revenue : 0,
            ];
        });

        return response()->json($data);
    }

    // 🧠 Revenue with filter: today, week, month, year
    public function revenueByFilter(Request $request)
    {
        $filter = $request->query('filter', 'month');
        $query = Order::query();

        switch ($filter) {
            case 'today':
                $query->whereDate('created_at', now());
                $format = '%H:00'; // hourly
                break;

            case 'week':
                $query->where('created_at', '>=', now()->startOfWeek());
                $format = '%a'; // Mon, Tue...
                break;

            case 'month':
                $query->whereMonth('created_at', now()->month);
                $format = '%d'; // day of month
                break;

            case 'year':
                $query->whereYear('created_at', now()->year);
                $format = '%b'; // Jan, Feb...
                break;

            default:
                return response()->json(['error' => 'Invalid filter'], 400);
        }

        $data = $query->selectRaw("DATE_FORMAT(created_at, '{$format}') as label, SUM(total_amount) as revenue")
            ->groupBy('label')
            ->orderByRaw("MIN(created_at)")
            ->get();

        return response()->json($data);
    }
}
