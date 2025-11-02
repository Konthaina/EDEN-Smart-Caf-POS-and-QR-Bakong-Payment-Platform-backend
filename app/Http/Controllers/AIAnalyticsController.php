<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AIAnalyticsController extends Controller
{
    // ... other methods ...
    public function inventoryHistory(Request $request)
    {
        $days = $request->query('days', 60);
        $startDate = Carbon::now()->subDays($days)->format('Y-m-d');

        $usage = DB::select("
            SELECT
                i.name AS ingredient,
                IFNULL(SUM(oi.quantity * r.quantity_used), 0) AS used
            FROM ingredients i
            LEFT JOIN recipes r ON i.id = r.ingredient_id
            LEFT JOIN order_items oi ON r.menu_item_id = oi.menu_item_id
            LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed' AND o.created_at >= ?
            GROUP BY i.name
            ORDER BY used DESC
        ", [$startDate]);

        return response()->json($usage);
    }

    public function ingredientStock()
    {
        $stock = DB::table('stocks')
            ->join('ingredients', 'stocks.ingredient_id', '=', 'ingredients.id')
            ->select('ingredients.name as ingredient', 'stocks.quantity as current_stock')
            ->get();
        return response()->json($stock);
    }
}

