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

    // 📈 Top 10 best-selling items (all time)
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

    // 📊 Weekly and monthly stats (recent)
    public function stats()
    {
        $weekly = Order::selectRaw('DATE(CONVERT_TZ(created_at, "+00:00", "+07:00")) as date, SUM(total_amount) as revenue')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $monthly = Order::selectRaw('MONTH(CONVERT_TZ(created_at, "+00:00", "+07:00")) as month, SUM(total_amount) as revenue')
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'weekly' => $weekly,
            'monthly' => $monthly
        ]);
    }

    // 📆 Monthly revenue bar chart
    public function monthlyRevenue()
    {
        $revenues = DB::table('orders')
            ->selectRaw("DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', '+07:00'), '%b') as month, SUM(total_amount) as revenue")
            ->whereYear('created_at', now()->year)
            ->groupBy('month')
            ->orderByRaw("STR_TO_DATE(month, '%b')")
            ->get();

        $months = collect([
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'May',
            'Jun',
            'Jul',
            'Aug',
            'Sep',
            'Oct',
            'Nov',
            'Dec'
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

    // 🧠 Revenue based on filter: today, week, month, year
    public function revenueByFilter(Request $request)
    {
        $filter = $request->query('filter', 'month');

        switch ($filter) {
            case 'today':
                $raw = Order::whereDate('created_at', now()->toDateString())
                    ->selectRaw("DATE_FORMAT(created_at, '%H:00') as label, SUM(total_amount) as revenue")
                    ->groupBy('label')
                    ->get()
                    ->keyBy('label');

                $data = collect(range(0, 23))->map(function ($hour) use ($raw) {
                    $label = str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00';
                    return [
                        'label' => $label,
                        'revenue' => (float) ($raw[$label]->revenue ?? 0),
                    ];
                });
                break;

            case 'week':
                $raw = Order::whereBetween('created_at', [
                    now()->startOfWeek()->startOfDay(),
                    now()->endOfWeek()->endOfDay(),
                ])
                    ->selectRaw("DATE_FORMAT(created_at, '%a') as label, SUM(total_amount) as revenue")
                    ->groupBy('label')
                    ->get();

                $weekdays = collect(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']);
                $data = $weekdays->map(function ($day) use ($raw) {
                    $match = $raw->firstWhere('label', $day);
                    return [
                        'label' => $day,
                        'revenue' => (float) ($match->revenue ?? 0),
                    ];
                });
                break;

            case 'month':
                $raw = Order::whereBetween('created_at', [
                    now()->startOfMonth()->startOfDay(),
                    now()->endOfMonth()->endOfDay(),
                ])
                    ->selectRaw("DATE_FORMAT(created_at, '%d') as label, SUM(total_amount) as revenue")
                    ->groupBy('label')
                    ->get();

                $daysInMonth = now()->daysInMonth;
                $data = collect(range(1, $daysInMonth))->map(function ($day) use ($raw) {
                    $label = str_pad($day, 2, '0', STR_PAD_LEFT);
                    $match = $raw->firstWhere('label', $label);
                    return [
                        'label' => $label,
                        'revenue' => (float) ($match->revenue ?? 0),
                    ];
                });
                break;

            case 'year':
                $raw = Order::whereBetween('created_at', [
                    now()->startOfYear()->startOfDay(),
                    now()->endOfYear()->endOfDay(),
                ])
                    ->selectRaw("DATE_FORMAT(created_at, '%b') as label, SUM(total_amount) as revenue")
                    ->groupBy('label')
                    ->get();

                $months = collect(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']);
                $data = $months->map(function ($month) use ($raw) {
                    $match = $raw->firstWhere('label', $month);
                    return [
                        'label' => $month,
                        'revenue' => (float) ($match->revenue ?? 0),
                    ];
                });
                break;

            default:
                return response()->json(['error' => 'Invalid filter'], 400);
        }

        return response()->json($data);
    }
    // In ReportController.php

    public function topItemsByFilter(Request $request)
    {
        $filter = $request->query('filter', 'month');
        $query = OrderItem::select('menu_items.name', DB::raw('SUM(order_items.quantity) as total_orders'))
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id');

        // Filter by date range
        switch ($filter) {
            case 'today':
                $query->whereDate('order_items.created_at', now());
                break;
            case 'week':
                $query->whereBetween('order_items.created_at', [now()->startOfWeek(), now()]);
                break;
            case 'month':
                $query->whereMonth('order_items.created_at', now()->month)
                    ->whereYear('order_items.created_at', now()->year);
                break;
            case 'year':
                $query->whereYear('order_items.created_at', now()->year);
                break;
            default:
                // Default to current month
                $query->whereMonth('order_items.created_at', now()->month)
                    ->whereYear('order_items.created_at', now()->year);
                break;
        }

        $topItems = $query
            ->groupBy('menu_items.name')
            ->orderByDesc('total_orders')
            ->limit(10)
            ->get();

        return response()->json($topItems);
    }
}
